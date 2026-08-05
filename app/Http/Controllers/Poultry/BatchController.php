<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBatchRequest;
use App\Http\Requests\UpdateBatchRequest;
use App\Models\AnimalGroup;
use App\Models\AuditLog;
use App\Models\Breed;
use App\Models\Department;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/*
 | Batches — the unit poultry is managed and costed by.
 |
 | Lists are scoped to the departments the viewer is posted to, so a Goats
 | worker never sees poultry batches even if they somehow reach the URL.
 */
class BatchController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = AnimalGroup::with(['breed', 'department'])
            ->visibleTo($user)
            ->orderByDesc('received_date');

        $status = $request->query('status', 'active');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($dept = $request->query('department')) {
            $query->where('department_id', $dept);
        }

        if ($term = trim((string) $request->query('q'))) {
            $query->where(function ($q) use ($term) {
                $q->where('code', 'ilike', "%{$term}%")
                  ->orWhere('name', 'ilike', "%{$term}%")
                  ->orWhere('house', 'ilike', "%{$term}%");
            });
        }

        $batches = $query->paginate(20)->withQueryString();

        // Live totals across everything visible, not just this page.
        $visible = AnimalGroup::visibleTo($user)->where('status', 'active');

        return view('poultry.batches.index', [
            'batches'     => $batches,
            'departments' => $this->departments($user),
            'filters'     => ['q' => $request->query('q'), 'department' => $dept, 'status' => $status],
            'totals'      => [
                'batches' => (clone $visible)->count(),
                'birds'   => (clone $visible)->sum('current_quantity'),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return view('poultry.batches.create', [
            'breeds'      => Breed::poultry()->where('is_active', true)->orderBy('name')->get(),
            'departments' => $this->departments($request->user()),
            'nextCode'    => $this->nextCode(),
        ]);
    }

    public function store(StoreBatchRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $data['code'] = $data['code'] ?: $this->nextCode();

        /*
         | Birds and money are two different figures.
         |
         | Suppliers throw in extras to cover early losses — 1,000 on the
         | invoice, 1,020 in the crate. The live count includes them,
         | because they are real birds eating real feed. The cost does not,
         | because they were not paid for.
         |
         | Cost per bird then spreads real money over real birds, which is
         | the honest number and the reason a supplier gives extras at all.
         */
        $free = (int) ($data['quantity_free'] ?? 0);

        $data['current_quantity'] = $data['quantity_received'] + $free;
        $data['total_cost']       = round($data['quantity_received'] * (float) $data['unit_cost'], 2);
        $data['created_by']       = $request->user()->id;
        $data['status']           = 'active';

        // Work out when the first eggs are due, from the breed's point of
        // lay and how old the birds already are on arrival.
        $data['expected_lay_date'] = $this->expectedLayDate(
            $data['breed_id'] ?? null,
            $data['received_date'],
            (int) ($data['age_days_on_arrival'] ?? 0)
        );

        $batch = AnimalGroup::create($data);

        AuditLog::record(
            'created', $batch,
            "Opened batch {$batch->code}: " . number_format($batch->totalReceived()) . ' birds'
                . ($free > 0 ? " (including {$free} free)." : '.'),
            null, $batch->only(['code', 'quantity_received', 'quantity_free', 'total_cost']),
            'batches'
        );

        return redirect()
            ->route('batches.show', $batch)
            ->with('status', "Batch {$batch->code} opened.");
    }

    public function show(Request $request, AnimalGroup $batch): View
    {
        $this->assertVisible($request, $batch);

        $batch->load(['breed', 'department', 'creator']);

        return view('poultry.batches.show', ['batch' => $batch]);
    }

    public function edit(Request $request, AnimalGroup $batch): View
    {
        $this->assertVisible($request, $batch);

        return view('poultry.batches.edit', [
            'batch'       => $batch,
            'breeds'      => Breed::poultry()->where('is_active', true)->orderBy('name')->get(),
            'departments' => $this->departments($request->user()),
        ]);
    }

    public function update(UpdateBatchRequest $request, AnimalGroup $batch): RedirectResponse
    {
        $this->assertVisible($request, $batch);

        $data   = $request->validated();
        $before = $batch->only(array_keys($data));

        // Only the paid birds carry cost. Free extras never do.
        $data['total_cost'] = round($data['quantity_received'] * (float) $data['unit_cost'], 2);

        $data['expected_lay_date'] = $this->expectedLayDate(
            $data['breed_id'] ?? null,
            $data['received_date'],
            (int) ($data['age_days_on_arrival'] ?? 0)
        );

        $batch->update($data);

        AuditLog::record('updated', $batch, "Updated batch {$batch->code}.",
            $before, $data, 'batches');

        return redirect()
            ->route('batches.show', $batch)
            ->with('status', 'Changes saved.');
    }

    /**
     * Close a batch. Nothing is deleted — the record and all its history
     * stay, which is what makes "what did B-001/26 cost and earn"
     * answerable a year later.
     */
    public function close(Request $request, AnimalGroup $batch): RedirectResponse
    {
        $this->assertVisible($request, $batch);

        abort_unless($request->user()->canDo('batches', 'edit'), 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ], [
            'reason.required' => 'Say why the batch is being closed.',
        ]);

        $batch->update([
            'status'      => 'closed',
            'closed_date' => now(),
            'notes'       => trim($batch->notes . "\n\nClosed: " . $data['reason']),
        ]);

        AuditLog::record('closed', $batch,
            "Closed batch {$batch->code}: {$data['reason']}", null, null, 'batches');

        return redirect()
            ->route('batches.show', $batch)
            ->with('status', "Batch {$batch->code} closed.");
    }

    /* ---------------------------------------------------------------- */

    private function assertVisible(Request $request, AnimalGroup $batch): void
    {
        $user = $request->user();

        if ($user->isSuperuser()) {
            return;
        }

        abort_unless(
            in_array($batch->department_id, $user->departmentIds(), true),
            403,
            'This batch belongs to a section you are not posted to.'
        );
    }

    private function departments($user)
    {
        $query = Department::where('level', 'department')->where('is_active', true);

        if (! $user->isSuperuser()) {
            $query->whereIn('id', $user->departmentIds());
        }

        return $query->orderBy('name')->get();
    }

    /**
     * When the first eggs are due: the breed's point of lay, counted from
     * hatch rather than from arrival.
     */
    private function expectedLayDate(?int $breedId, string $receivedDate, int $ageOnArrival): ?string
    {
        $breed = $breedId ? Breed::find($breedId) : null;

        if (! $breed || ! $breed->lay_start_week) {
            return null;
        }

        $daysToLay = ($breed->lay_start_week * 7) - $ageOnArrival;

        return Carbon::parse($receivedDate)
            ->addDays(max(0, $daysToLay))
            ->toDateString();
    }

    /**
     * Next batch code: B-001/26, B-002/26...
     *
     * The year makes a code readable long after the fact, and the sequence
     * restarts each year so numbers stay short. It is read from the highest
     * code in the CURRENT year only — deleting a batch can never cause a
     * duplicate, and last year's numbering is left alone.
     */
    private function nextCode(): string
    {
        $prefix = Setting::get('poultry.batch_prefix', 'B');
        $year   = now()->format('y');          // 26

        $last = AnimalGroup::withTrashed()
            ->where('code', 'like', "{$prefix}-%/{$year}")
            ->orderByRaw('LENGTH(code) DESC, code DESC')
            ->value('code');

        // B-007/26 -> 7
        $lastNumber = $last
            ? (int) substr($last, strlen($prefix) + 1, strpos($last, '/') - strlen($prefix) - 1)
            : 0;

        return sprintf('%s-%03d/%s', $prefix, $lastNumber + 1, $year);
    }
}