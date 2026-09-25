<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyVehicle;
use App\Models\FleetRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FleetController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('fleet'), 403);

        $vehicleSummaries=FleetRecord::orderBy('recorded_on')->get()->groupBy(fn($r)=>mb_strtoupper(trim($r->vehicle_id)))->map(function($records,$vehicle){$latest=$records->sortByDesc('id')->first();return ['vehicle'=>$vehicle,'distance'=>(float)$records->sum(fn($r)=>$r->distanceKm()??0),'fuel'=>(float)$records->sum('fuel_consumption_litres'),'odometer'=>$latest?->ending_odometer_km,'service_at'=>$latest?->next_service_odometer_km];})->values();
        return view('hr.fleet.index', [
            'records'=>FleetRecord::with('recorder')->latest('recorded_on')->latest('set_off_at')->paginate(30),
            'vehicles'=>CompanyVehicle::active()->orderBy('registration_number')->get(),
            'vehicleSummaries'=>$vehicleSummaries,
            'canRecord'=>$request->user()->canDo('fleet', 'create') || $request->user()->canDo('fleet', 'edit'),
        ]);
    }

    public function vehicles(Request $request): View
    {
        abort_unless($request->user()->canAccess('fleet'), 403);

        return view('hr.fleet.vehicles', [
            'vehicles' => CompanyVehicle::orderByDesc('is_active')->orderBy('registration_number')->get(),
            'canManage' => $request->user()->canDo('fleet', 'create') || $request->user()->canDo('fleet', 'edit'),
        ]);
    }

    public function storeVehicle(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('fleet', 'create') || $request->user()->canDo('fleet', 'edit'), 403);
        $request->merge(['registration_number' => mb_strtoupper(trim((string) $request->input('registration_number')))]);

        $data = $request->validate([
            'registration_number' => ['required', 'string', 'max:80', 'unique:company_vehicles,registration_number'],
            'make_model' => ['nullable', 'string', 'max:160'],
            'vehicle_type' => ['nullable', 'string', 'max:80'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'default_driver_name' => ['nullable', 'string', 'max:160'],
            'current_odometer_km' => ['nullable', 'numeric', 'min:0'],
            'next_service_odometer_km' => ['nullable', 'numeric', 'gte:current_odometer_km'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $vehicle = CompanyVehicle::create($data + ['is_active' => true, 'recorded_by' => $request->user()->id]);
        AuditLog::record('company_vehicle_registered', $vehicle, 'Company vehicle registered: '.$vehicle->registration_number, null, $data, 'fleet');

        return redirect()->route('hr.vehicles.index')->with('status', 'Company vehicle registered. It can now be selected for fleet journeys.');
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('fleet', 'create') || $request->user()->canDo('fleet', 'edit'), 403);
        $data=$request->validate([
            'recorded_on'=>['required','date','before_or_equal:today'],
            'vehicle_id'=>['required','string','max:80','exists:company_vehicles,registration_number'],
            'driver_name'=>['required','string','max:160'],
            'set_off_at'=>['nullable','date'],
            'pickup_point'=>['nullable','string','max:160'],
            'destination'=>['nullable','string','max:160'],
            'people_count'=>['nullable','integer','min:0'],
            'purpose'=>['required','string','max:1000'],
            'starting_odometer_km'=>['required','numeric','min:0'],
            'ending_odometer_km'=>['required','numeric','gte:starting_odometer_km'],
            'fuel_consumption_litres'=>['nullable','numeric','min:0'],
            'fuel_cost_ugx'=>['nullable','numeric','min:0'],
            'fuel_receipt'=>['nullable','file','mimes:jpg,jpeg,png,webp,pdf','max:5120'],
            'next_service_odometer_km'=>['nullable','numeric','gte:ending_odometer_km'],
            'vehicle_issues'=>['nullable','string','max:1000'],
            'maintenance'=>['nullable','string','max:1000'],
        ]);
        abort_unless(CompanyVehicle::active()->where('registration_number', $data['vehicle_id'])->exists(), 422, 'Choose an active registered company vehicle.');
        if($request->hasFile('fuel_receipt'))$data['fuel_receipt']=$request->file('fuel_receipt')->store('fleet/fuel-receipts','public');
        $previous=FleetRecord::whereRaw('lower(vehicle_id) = ?',[mb_strtolower(trim($data['vehicle_id']))])->whereNotNull('ending_odometer_km')->latest('recorded_on')->latest('id')->first();
        $difference=$previous?abs((float)$data['starting_odometer_km']-(float)$previous->ending_odometer_km):0;
        $record=FleetRecord::create($data+['recorded_by'=>$request->user()->id]);
        $vehicleUpdate = ['current_odometer_km' => $record->ending_odometer_km];
        if ($record->next_service_odometer_km !== null) {
            $vehicleUpdate['next_service_odometer_km'] = $record->next_service_odometer_km;
        }
        CompanyVehicle::where('registration_number', $record->vehicle_id)->update($vehicleUpdate);
        AuditLog::record('fleet_record_created',$record,'Fleet journey recorded for '.$record->vehicle_id,null,$data,'fleet');

        $message='Fleet record saved. Distance and fuel efficiency were calculated automatically.';
        if($difference>0.1)$message.=' Warning: the kilometre reading before this journey differs from the previous journey’s ending reading by '.number_format($difference,1).' km. Please verify both records.';
        return back()->with('status',$message);
    }
}
