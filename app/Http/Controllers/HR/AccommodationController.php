<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AccommodationController extends Controller
{
    /** Everyone staying at the farm now (checked into accommodation), with a headcount for the kitchen and welfare. */
    public function onFarm(Request $request): View
    {
        abort_unless($request->user()->canAccess('hr') || $request->user()->roles->pluck('code')->push($request->user()->role?->code)->contains('hr-manager'), 403);
        $builders = DB::table('construction_project_workers')->join('construction_projects', 'construction_projects.id', '=', 'construction_project_workers.construction_project_id')
            ->where('construction_projects.status', 'active')->pluck('construction_projects.name', 'construction_project_workers.employee_id');
        $people = DB::table('staff_accommodation_allocations as a')->join('employees as e', 'e.id', '=', 'a.employee_id')
            ->join('staff_hostel_rooms as r', 'r.id', '=', 'a.staff_hostel_room_id')->join('staff_hostels as h', 'h.id', '=', 'r.staff_hostel_id')
            ->whereNull('a.checked_out_on')->whereNull('e.deleted_at')
            ->select('e.id', 'e.first_name', 'e.last_name', 'e.staff_no', 'e.job_title', 'e.employment_type', 'e.on_payroll', 'e.expected_end_date', 'a.checked_in_on', 'a.expected_checkout_on', 'r.room_number', 'h.name as hostel')
            ->orderBy('h.name')->orderBy('r.room_number')->get()
            ->map(function ($p) use ($builders) { $p->project = $builders[$p->id] ?? null; return $p; });

        return view('hr.on-farm', ['people' => $people, 'byType' => $people->groupBy(fn ($p) => $p->project ? 'Builders' : ucfirst((string) $p->employment_type))->map->count()->sortDesc()]);
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('hr'), 403);
        $view = $request->query('view', 'rooms');
        abort_unless(in_array($view, ['overview', 'hostels', 'add-room', 'rooms', 'allocations', 'items'], true), 404);
        $hostels = DB::table('staff_hostels as h')->leftJoin('staff_hostel_rooms as r', fn($j)=>$j->on('r.staff_hostel_id','=','h.id')->where('r.is_active',true))->leftJoin('staff_accommodation_allocations as a', fn($j)=>$j->on('a.staff_hostel_room_id','=','r.id')->whereNull('a.checked_out_on'))->where('h.is_active',true)->groupBy('h.id','h.name','h.location','h.notes')->select('h.id','h.name','h.location','h.notes',DB::raw('coalesce(sum(r.bed_spaces),0) as capacity'),DB::raw('count(a.id) as occupied'))->orderBy('h.name')->get();
        $rooms = DB::table('staff_hostel_rooms as r')->join('staff_hostels as h','h.id','=','r.staff_hostel_id')->leftJoin('staff_accommodation_allocations as a', fn($j)=>$j->on('a.staff_hostel_room_id','=','r.id')->whereNull('a.checked_out_on'))->leftJoin('employees as e','e.id','=','a.employee_id')->where('r.is_active',true)->groupBy('r.id','h.name','r.room_number','r.bed_spaces','r.condition','r.notes')->select('r.id','h.name as hostel_name','r.room_number','r.bed_spaces','r.condition','r.notes',DB::raw('count(a.id) as occupied'),DB::raw("coalesce(string_agg(concat(e.first_name, ' ', e.last_name), ', '), '') as occupant_names"))->orderBy('h.name')->orderBy('r.room_number')->get();
        $allocations = DB::table('staff_accommodation_allocations as a')->join('staff_hostel_rooms as r','r.id','=','a.staff_hostel_room_id')->join('staff_hostels as h','h.id','=','r.staff_hostel_id')->join('employees as e','e.id','=','a.employee_id')->select('a.*','h.name as hostel_name','r.room_number',DB::raw("concat(e.first_name,' ',e.last_name) as employee_name"))->latest('a.checked_in_on')->limit(100)->get();
        $items = DB::table('staff_accommodation_items as i')->leftJoin('staff_hostel_rooms as r','r.id','=','i.staff_hostel_room_id')->leftJoin('staff_hostels as h','h.id','=','r.staff_hostel_id')->leftJoin('employees as e','e.id','=','i.employee_id')->select('i.*','h.name as hostel_name','r.room_number',DB::raw("concat(e.first_name,' ',e.last_name) as employee_name"))->latest('i.issued_on')->limit(100)->get();
        $hostelOptions = $hostels;
        $employees = Employee::active()->orderBy('first_name')->orderBy('last_name')->get();
        $filters = $request->only(['hostel','availability','condition','employee','status','from','to','q']);
        if ($view === 'overview') {
            $hostels = $hostels->filter(fn($hostel) => empty($filters['hostel']) || $hostel->name === $filters['hostel'])
                ->filter(fn($hostel) => empty($filters['availability']) || ($filters['availability'] === 'available' ? $hostel->capacity > $hostel->occupied : $hostel->capacity <= $hostel->occupied));
        }
        if ($view === 'rooms') {
            $rooms = $rooms->filter(fn($room) => empty($filters['hostel']) || $room->hostel_name === $filters['hostel'])
                ->filter(fn($room) => empty($filters['condition']) || $room->condition === $filters['condition'])
                ->filter(fn($room) => empty($filters['availability']) || ($filters['availability'] === 'available' ? $room->occupied < $room->bed_spaces : $room->occupied >= $room->bed_spaces));
        }
        if ($view === 'allocations') {
            $allocations = $allocations->filter(fn($allocation) => empty($filters['hostel']) || $allocation->hostel_name === $filters['hostel'])
                ->filter(fn($allocation) => empty($filters['employee']) || (string) $allocation->employee_id === (string) $filters['employee'])
                ->filter(fn($allocation) => empty($filters['status']) || ($filters['status'] === 'active' ? !$allocation->checked_out_on : (bool) $allocation->checked_out_on))
                ->filter(fn($allocation) => empty($filters['from']) || $allocation->checked_in_on >= $filters['from'])
                ->filter(fn($allocation) => empty($filters['to']) || $allocation->checked_in_on <= $filters['to']);
        }
        if ($view === 'items') {
            $items = $items->filter(fn($item) => empty($filters['hostel']) || $item->hostel_name === $filters['hostel'])
                ->filter(fn($item) => empty($filters['employee']) || (string) $item->employee_id === (string) $filters['employee'])
                ->filter(fn($item) => empty($filters['q']) || str_contains(mb_strtolower($item->item_name), mb_strtolower($filters['q'])))
                ->filter(fn($item) => empty($filters['status']) || match ($filters['status']) {'assigned' => $item->is_returnable && !$item->returned_on, 'returned' => (bool) $item->returned_on, 'consumed' => !$item->is_returnable, default => true});
        }
        return view('hr.accommodation.index', compact('view','hostels','hostelOptions','rooms','allocations','items','employees','filters') + ['canManage'=>$request->user()->canDo('hr','create')||$request->user()->canDo('hr','edit')]);
    }
    public function storeHostel(Request $r): RedirectResponse { $this->manage($r); $d=$r->validate(['name'=>['required','string','max:120','unique:staff_hostels,name'],'location'=>['nullable','string','max:160'],'notes'=>['nullable','string','max:1000']]); DB::table('staff_hostels')->insert($d+['is_active'=>true,'created_at'=>now(),'updated_at'=>now()]); return back()->with('status','Hostel added. Add its rooms and bed spaces.'); }
    public function storeRoom(Request $r): RedirectResponse { $this->manage($r); $d=$r->validate(['staff_hostel_id'=>['required','exists:staff_hostels,id'],'room_number'=>['required','string','max:40',Rule::unique('staff_hostel_rooms')->where(fn($q)=>$q->where('staff_hostel_id',$r->staff_hostel_id))],'bed_spaces'=>['required','integer','min:1','max:100'],'condition'=>['required','in:good,needs_attention,unavailable'],'notes'=>['nullable','string','max:1000']]); DB::table('staff_hostel_rooms')->insert($d+['is_active'=>true,'created_at'=>now(),'updated_at'=>now()]); return back()->with('status','Room and bed capacity added.'); }
    public function assign(Request $r): RedirectResponse
    {
        $this->manage($r);
        $d = $r->validate([
            'staff_hostel_room_id' => ['required', 'exists:staff_hostel_rooms,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'checked_in_on' => ['required', 'date', 'before_or_equal:today'],
            'expected_checkout_on' => ['nullable', 'date', 'after_or_equal:checked_in_on'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'item_name' => ['nullable', 'required_with:quantity', 'string', 'max:120'],
            'quantity' => ['nullable', 'required_with:item_name', 'numeric', 'min:0.01'],
            'issued_on' => ['nullable', 'date', 'after_or_equal:checked_in_on', 'before_or_equal:today'],
            'is_returnable' => ['nullable', 'boolean'],
        ]);

        return DB::transaction(function () use ($d, $r) {
            $room = DB::table('staff_hostel_rooms')->where('id', $d['staff_hostel_room_id'])->lockForUpdate()->first();
            abort_if(!$room || !$room->is_active || $room->condition === 'unavailable', 422, 'Choose an available room.');
            $occupied = DB::table('staff_accommodation_allocations')->where('staff_hostel_room_id', $room->id)->whereNull('checked_out_on')->count();
            abort_if($occupied >= $room->bed_spaces, 422, 'This room has no available bed space.');
            abort_if(DB::table('staff_accommodation_allocations')->where('employee_id', $d['employee_id'])->whereNull('checked_out_on')->exists(), 422, 'This worker already has an active accommodation assignment.');

            DB::table('staff_accommodation_allocations')->insert([
                'staff_hostel_room_id' => $room->id, 'employee_id' => $d['employee_id'],
                'checked_in_on' => $d['checked_in_on'], 'expected_checkout_on' => $d['expected_checkout_on'] ?? null,
                'notes' => $d['notes'] ?? null, 'recorded_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (!empty($d['item_name'])) {
                DB::table('staff_accommodation_items')->insert([
                    'staff_hostel_room_id' => $room->id, 'employee_id' => $d['employee_id'],
                    'item_name' => $d['item_name'], 'quantity' => $d['quantity'],
                    'is_returnable' => (bool) ($d['is_returnable'] ?? false), 'issued_on' => $d['issued_on'] ?? $d['checked_in_on'],
                    'notes' => $d['notes'] ?? null, 'recorded_by' => $r->user()->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return back()->with('status', !empty($d['item_name']) ? 'Worker checked in and item issue recorded.' : 'Worker checked into the room.');
        });
    }
    public function checkout(Request $r, int $allocation): RedirectResponse { $this->manage($r); $d=$r->validate(['checked_out_on'=>['required','date','before_or_equal:today'],'checkout_reason'=>['nullable','string','max:160']]); $row=DB::table('staff_accommodation_allocations')->where('id',$allocation)->whereNull('checked_out_on')->first(); abort_unless($row,404); abort_if($d['checked_out_on']<$row->checked_in_on,422,'Checkout cannot be before check-in.'); DB::table('staff_accommodation_allocations')->where('id',$allocation)->update($d+['updated_at'=>now()]); return back()->with('status','Worker checked out. The bed space is available again.'); }
    public function storeItem(Request $r): RedirectResponse { $this->manage($r); $d=$r->validate(['staff_hostel_room_id'=>['nullable','exists:staff_hostel_rooms,id'],'employee_id'=>['nullable','exists:employees,id'],'item_name'=>['required','string','max:120'],'quantity'=>['required','numeric','min:0.01'],'is_returnable'=>['nullable','boolean'],'issued_on'=>['required','date','before_or_equal:today'],'notes'=>['nullable','string','max:1000']]); abort_if(empty($d['staff_hostel_room_id'])&&empty($d['employee_id']),422,'Choose a room or employee receiving the item.'); $d['is_returnable']=(bool)($d['is_returnable']??false); DB::table('staff_accommodation_items')->insert($d+['recorded_by'=>$r->user()->id,'created_at'=>now(),'updated_at'=>now()]); return back()->with('status',$d['is_returnable']?'Returnable hostel item assigned.':'Consumable welfare item issued.'); }
    public function returnItem(Request $r,int $item): RedirectResponse { $this->manage($r); $d=$r->validate(['returned_on'=>['required','date','before_or_equal:today'],'condition_on_return'=>['required','string','max:60']]); $updated=DB::table('staff_accommodation_items')->where('id',$item)->where('is_returnable',true)->whereNull('returned_on')->update($d+['updated_at'=>now()]); abort_unless($updated,404); return back()->with('status','Accommodation item marked as returned.'); }
    private function manage(Request $r): void { abort_unless($r->user()->canDo('hr','create')||$r->user()->canDo('hr','edit'),403); }
}
