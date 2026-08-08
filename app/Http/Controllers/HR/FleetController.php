<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FleetRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FleetController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->canAccess('fleet'), 403);

        return view('hr.fleet.index', [
            'records'=>FleetRecord::with('recorder')->latest('recorded_on')->latest('set_off_at')->paginate(30),
            'canRecord'=>$request->user()->canDo('fleet', 'create') || $request->user()->canDo('fleet', 'edit'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canDo('fleet', 'create') || $request->user()->canDo('fleet', 'edit'), 403);
        $data=$request->validate([
            'recorded_on'=>['required','date','before_or_equal:today'],
            'vehicle_id'=>['required','string','max:80'],
            'driver_name'=>['required','string','max:160'],
            'set_off_at'=>['nullable','date'],
            'pickup_point'=>['nullable','string','max:160'],
            'destination'=>['nullable','string','max:160'],
            'people_count'=>['nullable','integer','min:0'],
            'purpose'=>['required','string','max:1000'],
            'fuel_consumption_litres'=>['nullable','numeric','min:0'],
            'fuel_receipt'=>['nullable','string','max:100'],
            'vehicle_issues'=>['nullable','string','max:1000'],
            'maintenance'=>['nullable','string','max:1000'],
        ]);
        $record=FleetRecord::create($data+['recorded_by'=>$request->user()->id]);
        AuditLog::record('fleet_record_created',$record,'Fleet journey recorded for '.$record->vehicle_id,null,$data,'fleet');

        return back()->with('status','Fleet record saved.');
    }
}
