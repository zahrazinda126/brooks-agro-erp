<?php

namespace App\Http\Controllers\Poultry;

use App\Http\Controllers\Controller;
use App\Models\AnimalGroup;
use App\Models\Employee;
use App\Models\PoultryHouse;
use App\Models\PoultryHouseAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class HouseController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeProductionManager($request);

        $workers = Employee::where('status', 'active')
            ->where(function ($query) {
                $query->whereHas('user.roles', fn ($roles) => $roles->whereIn('code', \App\Models\User::DATA_ENTRY_ROLES))
                    ->orWhereHas('user.role', fn ($role) => $role->whereIn('code', \App\Models\User::DATA_ENTRY_ROLES));
            })
            ->orderBy('first_name')
            ->get();

        return view('poultry.houses.index', [
            // Natural order: House 1, House 2 … House 10.
            'houses' => PoultryHouse::with(['batch', 'activeAssignments.employee'])
                ->get()
                ->sortBy('house_number', SORT_NATURAL | SORT_FLAG_CASE)
                ->values(),
            'batches' => AnimalGroup::where('status', 'active')->orderBy('code')->get(),
            'workers' => $workers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeProductionManager($request);
        $data = $request->validate([
            'house_number' => ['required', 'string', 'max:60', 'unique:poultry_houses'],
            'house_type' => ['required', 'in:brooder,standard'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        PoultryHouse::create($data + ['animal_group_id' => null, 'bird_quantity' => 0, 'is_active' => true]);

        return back()->with('status', 'Poultry house added.');
    }

    public function update(Request $request, PoultryHouse $house): RedirectResponse
    {
        $this->authorizeProductionManager($request);
        $data = $request->validate([
            'house_number' => ['required', 'string', 'max:60', 'unique:poultry_houses,house_number,'.$house->id],
            'house_type' => ['required', 'in:brooder,standard'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $house->update($data + ['is_active' => (bool) ($data['is_active'] ?? false)]);

        return back()->with('status', 'House details updated.');
    }

    public function assign(Request $request): RedirectResponse
    {
        $this->authorizeProductionManager($request);
        $data = $request->validate([
            'poultry_house_id' => ['required', 'exists:poultry_houses,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'assigned_from' => ['required', 'date'],
        ]);

        $activeAssignments = PoultryHouseAssignment::where('employee_id', $data['employee_id'])
            ->whereNull('assigned_until');

        if ($activeAssignments->count() >= 2) {
            return back()->withErrors(['employee_id' => 'A poultry worker can be assigned to a maximum of two active houses.']);
        }

        if ((clone $activeAssignments)->where('poultry_house_id', $data['poultry_house_id'])->exists()) {
            return back()->withErrors(['employee_id' => 'This worker is already assigned to that house.']);
        }

        PoultryHouseAssignment::create($data + ['assigned_by' => $request->user()->id]);

        return back()->with('status', 'Worker assigned to house.');
    }

    public function end(Request $request, PoultryHouseAssignment $assignment): RedirectResponse
    {
        $this->authorizeProductionManager($request);
        $assignment->update(['assigned_until' => now()->toDateString()]);

        return back()->with('status', 'House assignment ended.');
    }

    public function transfer(Request $request): RedirectResponse
    {
        $this->authorizeProductionManager($request);
        $data = $request->validate([
            'from_house_id' => ['required', 'exists:poultry_houses,id'],
            'to_house_id' => ['required', 'different:from_house_id', 'exists:poultry_houses,id'],
            'bird_quantity' => ['required', 'integer', 'min:1'],
            'moved_on' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:180'],
        ]);

        DB::transaction(function () use ($data, $request) {
            $from = PoultryHouse::lockForUpdate()->findOrFail($data['from_house_id']);
            $to = PoultryHouse::lockForUpdate()->findOrFail($data['to_house_id']);
            abort_unless($from->animal_group_id, 422, 'The source house has no batch.');
            abort_if($data['bird_quantity'] > $from->bird_quantity, 422, 'The transfer exceeds the birds in the source house.');
            abort_if($to->animal_group_id && $to->animal_group_id !== $from->animal_group_id, 422, 'The destination contains a different batch.');

            $batchId = $from->animal_group_id;
            $batch = AnimalGroup::findOrFail($batchId);
            abort_if(\Illuminate\Support\Carbon::parse($data['moved_on'])->isBefore($batch->received_date), 422,
                'The movement date cannot be before the batch arrived.');
            $ageOnMovementDate = $batch->ageInDays(\Illuminate\Support\Carbon::parse($data['moved_on']));
            abort_if($to->house_type === 'standard' && $ageOnMovementDate < config('farm.brooder_max_age_days'), 422,
                'These chicks are only '.$ageOnMovementDate.' days old. Transfer to a standard house is allowed from '.config('farm.brooder_max_age_days').' days.');
            $from->decrement('bird_quantity', $data['bird_quantity']);
            $from->refresh();
            if ($from->bird_quantity === 0) {
                $from->update(['animal_group_id' => null]);
            }
            $to->update(['animal_group_id' => $batchId, 'bird_quantity' => $to->bird_quantity + $data['bird_quantity']]);

            DB::table('poultry_house_movements')->insert([
                'animal_group_id' => $batchId,
                'from_house_id' => $from->id,
                'to_house_id' => $to->id,
                'bird_quantity' => $data['bird_quantity'],
                'moved_on' => $data['moved_on'],
                'reason' => $data['reason'] ?: 'Brooder or poultry-house transfer',
                'moved_by' => $request->user()->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $batch->update([
                'house' => PoultryHouse::where('animal_group_id', $batchId)->orderBy('house_number')->pluck('house_number')->join(', '),
            ]);
        });

        return back()->with('status', 'Birds transferred and movement history saved.');
    }

    private function authorizeProductionManager(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->role?->code === 'production-manager'
                || $user->roles->contains('code', 'production-manager'),
            403
        );
    }
}
