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
use App\Models\PoultryHouse;
use App\Models\BatchOrder;
use Illuminate\Support\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use App\Services\CommunicationService;

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
        $from = $request->query('from');
        $to = $request->query('to');
        if ($from) $query->whereDate('received_date', '>=', $from);
        if ($to) $query->whereDate('received_date', '<=', $to);

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
            'filters'     => ['q' => $request->query('q'), 'department' => $dept, 'status' => $status, 'from'=>$from, 'to'=>$to],
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
            'availableHouses' => PoultryHouse::where('is_active', true)
                ->whereNull('animal_group_id')->orderBy('house_number')->get(),
            'batchOrders'=>BatchOrder::with('payments')->where('batch_type','poultry')->whereNull('animal_group_id')->whereIn('status',['ordered','partly_paid','paid_before_arrival'])->orderBy('expected_on')->get(),
        ]);
    }

    public function store(StoreBatchRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $batchOrderId=$data['batch_order_id']??null;
        $batchOrder=$batchOrderId?BatchOrder::with('payments')->findOrFail($batchOrderId):null;
        unset($data['batch_order_id']);
        if($batchOrder){$data['supplier_name']=$batchOrder->supplier_name;$data['total_cost']=$batchOrder->order_total;unset($data['unit_cost']);if(blank($data['breed_name']??null))$data['breed_name']=$batchOrder->breed;}
        $data['breed_id'] = $this->resolveBreed($data['breed_name'] ?? null, $data['purpose']);
        unset($data['breed_name']);

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

        $transitDeaths = (int) ($data['transit_deaths'] ?? 0);
        $data['current_quantity'] = $data['quantity_received'] + $free - $transitDeaths;
        $data['expected_brooder_exit_date'] = (int) $data['age_days_on_arrival'] <= 42
            ? Carbon::parse($data['received_date'])->addDays(42 - (int) $data['age_days_on_arrival'])->toDateString()
            : null;
        if (isset($data['total_cost'])) {
            $data['unit_cost'] = round((float) $data['total_cost'] / $data['quantity_received'], 2);
        } else {
            $data['unit_cost'] = (float) ($data['unit_cost'] ?? 0);
            $data['total_cost'] = round($data['quantity_received'] * $data['unit_cost'], 2);
        }
        $data['created_by']       = $request->user()->id;
        $data['status']           = 'active';

        // Work out when the first eggs are due, from the breed's point of
        // lay and how old the birds already are on arrival.
        $data['expected_lay_date'] = $this->expectedLayDate(
            $data['breed_id'] ?? null,
            $data['received_date'],
            (int) ($data['age_days_on_arrival'] ?? 0)
        );

        $allocations = collect($data['house_allocations'] ?? [])->map(fn ($quantity) => (int) $quantity)->filter();
        $newHouses = collect($data['new_houses'] ?? [])->filter(fn ($house) =>
            trim((string) ($house['house_number'] ?? '')) !== '' && (int) ($house['bird_quantity'] ?? 0) > 0
        );
        unset($data['house_allocations']);
        unset($data['new_houses']);

        $batch = DB::transaction(function () use ($data, $allocations, $newHouses, $batchOrderId) {
            $houses = PoultryHouse::whereIn('id', $allocations->keys())
                ->where('is_active', true)->whereNull('animal_group_id')
                ->lockForUpdate()->get()->keyBy('id');

            abort_if($houses->count() !== $allocations->count(), 422,
                'One of the selected houses is no longer available. Refresh the page and choose again.');

            $batch = AnimalGroup::create($data);
            if($batchOrderId){$order=BatchOrder::whereKey($batchOrderId)->whereNull('animal_group_id')->lockForUpdate()->firstOrFail();$order->update(['animal_group_id'=>$batch->id,'status'=>'arrived']);}
            foreach ($allocations as $houseId => $quantity) {
                $houses[$houseId]->update(['animal_group_id' => $batch->id, 'bird_quantity' => $quantity]);
                DB::table('poultry_house_movements')->insert(['animal_group_id' => $batch->id, 'from_house_id' => null, 'to_house_id' => $houseId, 'bird_quantity' => $quantity, 'moved_on' => $batch->received_date, 'reason' => 'Initial arrival allocation', 'moved_by' => $batch->created_by, 'created_at' => now(), 'updated_at' => now()]);
            }
            $createdHouses = collect();
            foreach ($newHouses as $houseData) {
                $createdHouses->push(PoultryHouse::create([
                    'house_number' => trim($houseData['house_number']),
                    'house_type' => $houseData['house_type'] ?? 'standard',
                    'animal_group_id' => $batch->id,
                    'bird_quantity' => (int) $houseData['bird_quantity'],
                    'is_active' => true,
                ]));
            }
            foreach ($createdHouses as $house) {
                DB::table('poultry_house_movements')->insert(['animal_group_id' => $batch->id, 'from_house_id' => null, 'to_house_id' => $house->id, 'bird_quantity' => $house->bird_quantity, 'moved_on' => $batch->received_date, 'reason' => 'Initial arrival allocation', 'moved_by' => $batch->created_by, 'created_at' => now(), 'updated_at' => now()]);
            }
            $batch->update(['house' => $houses->pluck('house_number')->merge($createdHouses->pluck('house_number'))->join(', ')]);

            return $batch;
        });

        AuditLog::record(
            'created', $batch,
            "Opened batch {$batch->code}: " . number_format($batch->liveReceived()) . ' live birds'
                . ($free > 0 ? " (including {$free} free)." : '.'),
            null, $batch->only(['code', 'quantity_received', 'quantity_free', 'transit_deaths', 'total_cost']),
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
        abort_unless($batch->mayBeEditedBy($request->user()), 403, 'The Director must allow you to edit this batch first.');

        return view('poultry.batches.edit', [
            'batch'       => $batch,
            'breeds'      => Breed::poultry()->where('is_active', true)->orderBy('name')->get(),
            'departments' => $this->departments($request->user()),
        ]);
    }

    public function update(UpdateBatchRequest $request, AnimalGroup $batch): RedirectResponse
    {
        $this->assertVisible($request, $batch);
        abort_unless($batch->mayBeEditedBy($request->user()), 403, 'The Director must allow you to edit this batch first.');

        $data   = $request->validated();
        $data['breed_id'] = $this->resolveBreed($data['breed_name'] ?? null, $data['purpose']);
        unset($data['breed_name']);
        $before = $batch->only(array_keys($data));

        // Only the paid birds carry cost. Free extras never do.
        if ($request->user()->isProductionUser()) {
            // Arrival corrections must not overwrite Finance's recorded costs.
            unset($data['unit_cost'], $data['total_cost']);
        } elseif (isset($data['total_cost'])) {
            $data['unit_cost'] = round((float) $data['total_cost'] / $data['quantity_received'], 2);
        } else {
            $data['total_cost'] = round($data['quantity_received'] * (float) $data['unit_cost'], 2);
        }

        $data['expected_lay_date'] = $this->expectedLayDate(
            $data['breed_id'] ?? null,
            $data['received_date'],
            (int) ($data['age_days_on_arrival'] ?? 0)
        );

        $batch->update($data + [
            // Director approval covers this correction only. A later change
            // requires a fresh request and remains independently auditable.
            'edit_requested_by' => null, 'edit_requested_at' => null,
            'edit_authorized_by' => null, 'edit_authorized_for' => null,
            'edit_authorized_until' => null,
        ]);

        AuditLog::record('updated', $batch, "Updated batch {$batch->code}.",
            $before, $data, 'batches');

        return redirect()
            ->route('batches.show', $batch)
            ->with('status', 'Changes saved.');
    }

    public function requestEdit(Request $request, AnimalGroup $batch, CommunicationService $communications): RedirectResponse
    {
        $this->assertVisible($request, $batch);
        abort_unless($request->user()->canDo('batches', 'edit'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $batch->update(['edit_requested_by' => $request->user()->id, 'edit_requested_at' => now(), 'edit_authorized_by' => null, 'edit_authorized_for' => null, 'edit_authorized_until' => null]);
        $communications->toRole('director', "Batch {$batch->code} edit requested", $request->user()->displayName()." requested permission to edit this batch. Reason: {$data['reason']}", $request->user()->id, route('batches.show', $batch, false));
        AuditLog::record('edit_requested', $batch, "Edit permission requested: {$data['reason']}", null, null, 'batches');
        return back()->with('status', 'The edit request was sent to the Director.');
    }

    public function authorizeEdit(Request $request, AnimalGroup $batch, CommunicationService $communications): RedirectResponse
    {
        $roles = $request->user()->roles->pluck('code')->push($request->user()->role?->code);
        abort_unless($roles->contains('director'), 403);
        abort_unless($batch->edit_requested_by, 422, 'No manager has requested edit access for this batch.');
        $data = $request->validate(['hours' => ['required', 'integer', 'min:1', 'max:72']]);
        $until = now()->addHours((int) $data['hours']);
        $managerId = $batch->edit_requested_by;
        $batch->update(['edit_authorized_by' => $request->user()->id, 'edit_authorized_for' => $managerId, 'edit_authorized_until' => $until]);
        $communications->toUser($managerId, "Batch {$batch->code} editing allowed", 'The Director allowed editing until '.$until->format('d M Y, H:i').'.', 'workflow', $request->user()->id, route('batches.edit', $batch, false));
        AuditLog::record('edit_authorized', $batch, 'Director allowed editing until '.$until->toDateTimeString().'.', null, null, 'batches');
        return back()->with('status', 'Editing permission granted to the requesting manager.');
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

        $roleCodes = $user->roles->pluck('code')->push($user->role?->code);
        if ($user->isSuperuser() || $roleCodes->contains('director')) {
            return;
        }

        abort_unless(
            in_array($batch->department_id, $user->operationalDepartmentIds(), true),
            403,
            'This batch belongs to a section you are not posted to.'
        );
    }

    private function departments($user)
    {
        $query = Department::where('level', 'department')->where('is_active', true);

        if (! $user->isSuperuser()) {
            $query->whereIn('id', $user->operationalDepartmentIds());
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

    private function resolveBreed(?string $name, string $purpose): ?int
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        $existing = Breed::poultry()->whereRaw('lower(name) = ?', [Str::lower($name)])->first();
        if ($existing) {
            return $existing->id;
        }

        $baseCode = Str::upper(Str::slug($name, '-')) ?: 'POULTRY-BREED';
        $code = $baseCode;
        $suffix = 2;
        while (Breed::where('code', $code)->exists()) {
            $code = $baseCode.'-'.$suffix++;
        }

        return Breed::create([
            'code' => $code,
            'name' => $name,
            'species' => 'poultry',
            'purpose' => $purpose,
            'is_active' => true,
            'notes' => 'Added during poultry batch registration.',
        ])->id;
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
