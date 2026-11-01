@pushOnce('styles')<style>
.legacy-hr{background:#fff;border:1px solid #aaa;padding:10px;color:#111;font:13px Arial,sans-serif}.legacy-hr__scroll{overflow-x:auto;padding-bottom:8px}.legacy-hr__sheet{min-width:1180px}.legacy-hr h3{text-align:center;font-size:17px;margin:5px 0 16px;text-transform:uppercase}.legacy-hr h4{font-size:14px;margin:18px 0 5px}.legacy-hr table{width:100%;border-collapse:collapse;background:#fff}.legacy-hr th,.legacy-hr td{border:1px solid #222;padding:5px 6px;vertical-align:middle;color:#111}.legacy-hr th{background:#c6e0b4;text-align:center;font-weight:700}.legacy-hr .num{text-align:center;white-space:nowrap}.legacy-hr .narrative{padding:8px 3px;white-space:pre-line}.legacy-hr .issue{color:#e11;font-weight:600}.legacy-hr .empty{color:#8b8178;text-align:center}.legacy-hr .section-no{display:inline-block;min-width:20px}.legacy-hr .wide{min-width:1250px}.legacy-hr .attendance-summary{display:flex;gap:28px;align-items:center;margin:0 0 7px;padding:8px 10px;border:1px solid #222;background:#eef5e9}.legacy-hr .attendance-summary strong{margin-left:6px}.legacy-hr__response{display:block;width:100%;min-height:88px;padding:10px;border:1px solid #222;background:#fff;color:#111;font:inherit;resize:vertical}
</style>@endPushOnce
@php
$attendance=$snapshot['records']->where('type','attendance')->keyBy('employee_id');
$securityStaff=$snapshot['employees']->filter(fn($employee)=>Str::contains(Str::lower(($employee->job_title??'').' '.($employee->role_name??'')),['security','guard']));
$welfare=$snapshot['records']->where('type','welfare');
$sectionText=function($code,$field)use($departmentRows){return optional($departmentRows->get($code))->{$field};};
$detailRows=function($code,$key)use($departmentRows){return collect(data_get($departmentRows->get($code)?->details,$key,[]))->filter(fn($row)=>collect($row)->filter(fn($value)=>filled($value))->isNotEmpty());};
$securityEntries=$detailRows('security','security');
$mealEntries=$detailRows('welfare','meals');
$projectEntries=$detailRows('construction','projects');
$adHocEntries=$detailRows('ad_hoc','activities');
$attendanceExceptions=$detailRows('attendance','attendance')->keyBy(fn($row)=>Str::lower(trim($row['staff_no']??'')));
$managementObservations=collect(data_get($departmentRows->get('ad_hoc')?->details,'management_observations',[]));
@endphp
<div class="legacy-hr"><div class="legacy-hr__scroll"><div class="legacy-hr__sheet">
 @include('partials.report-letterhead', ['title' => 'Human Resource & Administration Daily Report'])
 <h3>Human Resource and Administration Report for {{ \Carbon\Carbon::parse($date)->format('jS F Y') }}</h3>

 <h4><span class="section-no">1</span>Staff Attendance Report</h4>
 <div class="attendance-summary"><span>Deliverable:<strong>Employee attendance</strong></span><span>Number of workers at the farm:<strong>{{ $snapshot['active_staff'] }}</strong></span></div>
 <table class="wide attendance-table"><thead><tr><th>No.</th><th>Employee Name</th><th>Department / Unit</th><th>Position</th><th>Worker type</th><th>ID Number</th><th>Attendance status</th><th>Leave type</th><th>Reliever</th><th>Total leave days</th><th>Leave start</th><th>Leave end</th><th>Remarks</th></tr></thead><tbody>
 @forelse($snapshot['employees'] as $index=>$employee)
 @php
 $record=$attendance->get($employee->id);
 $leave=$snapshot['active_leave']->get($employee->id);
 $exception=$attendanceExceptions->get(Str::lower($employee->staff_no));
 @endphp
 <tr><td class="num">{{ $index+1 }}</td><td>{{ trim($employee->first_name.' '.$employee->last_name.' '.$employee->other_names) }}</td><td>{{ $employee->department_name ?: 'Not assigned' }}</td><td>{{ $employee->job_title ?: $employee->role_name ?: '—' }}</td><td>{{ match($employee->employment_type){'casual'=>'Casual worker','intern'=>'Intern','student'=>'Student','vacist'=>'Holiday worker','temporary'=>'Temporary worker','other'=>'Other worker',default=>Str::headline($employee->employment_type)} }}</td><td>{{ $employee->staff_no }}</td><td>{{ $exception['attendance_status']??($leave ? 'Absent' : ($record ? 'Present' : 'Not recorded')) }}</td><td>{{ $exception['leave_type']??($leave ? ($leave->title ?: $leave->details ?: 'Leave') : 'n/a') }}</td><td>{{ $exception['reliever']??'—' }}</td><td class="num">{{ !empty($exception['leave_start'])&&!empty($exception['leave_end']) ? \Carbon\Carbon::parse($exception['leave_start'])->diffInDays(\Carbon\Carbon::parse($exception['leave_end']))+1 : ($leave ? \Carbon\Carbon::parse($leave->recorded_on)->diffInDays(\Carbon\Carbon::parse($leave->ends_on ?: $leave->recorded_on))+1 : '—') }}</td><td>{{ !empty($exception['leave_start']) ? \Carbon\Carbon::parse($exception['leave_start'])->format('d/m/Y') : ($leave ? \Carbon\Carbon::parse($leave->recorded_on)->format('d/m/Y') : '—') }}</td><td>{{ !empty($exception['leave_end']) ? \Carbon\Carbon::parse($exception['leave_end'])->format('d/m/Y') : ($leave?->ends_on ? \Carbon\Carbon::parse($leave->ends_on)->format('d/m/Y') : '—') }}</td><td>{{ $exception['remarks']??($leave?->details ?: $record?->details ?: '—') }}</td></tr>
 @empty<tr><td colspan="13" class="empty">No active workers are registered.</td></tr>@endforelse
 </tbody></table>

 <h4><span class="section-no">2</span>Security Attendance Report</h4>
 <table><thead><tr><th>Deliverable</th><th>Number of security guards at the farm</th><th>Walkie-talkie No.</th><th>Name</th><th>Shift</th><th>Station</th><th>Attendance (present only)</th><th>Reporting time</th><th>Challenge</th><th>Remarks / reason for variation</th></tr></thead><tbody>
 @if($securityEntries->isNotEmpty()) @foreach($securityEntries as $entry)<tr><td>Security</td><td class="num">{{ $securityEntries->count() }}</td><td>{{ $entry['walkie_talkie']??'—' }}</td><td>{{ $entry['name']??'—' }}</td><td>{{ $entry['shift']??'—' }}</td><td>{{ $entry['station']??'—' }}</td><td>{{ $entry['attendance']??'—' }}</td><td>{{ $entry['reporting_time']??'—' }}</td><td>{{ $entry['challenge']??'—' }}</td><td>{{ $entry['remarks']??'—' }}</td></tr>@endforeach @else
 @forelse($securityStaff as $guard) @php $record=$attendance->get($guard->id); @endphp
 <tr><td>Security</td><td class="num">{{ $securityStaff->count() }}</td><td class="num">—</td><td>{{ trim($guard->first_name.' '.$guard->last_name) }}</td><td class="num">—</td><td class="num">—</td><td class="num">{{ $record ? 'Present' : '—' }}</td><td class="num">{{ $record?->created_at ? \Carbon\Carbon::parse($record->created_at)->format('H:i') : '—' }}</td><td>{{ $record?->title ?: '—' }}</td><td>{{ $record?->details ?: '—' }}</td></tr>
 @empty<tr><td>Security</td><td class="num">0</td><td colspan="8" class="empty">No security staff or attendance was recorded.</td></tr>@endforelse
 @endif
 </tbody></table>

 <h4><span class="section-no">3</span>Welfare Report</h4>
 <table><thead><tr><th>Deliverable</th><th>Time for food deliveries</th><th>Number of people preparing the meals</th><th>Number of people serving the meals</th><th>Meals served</th><th>Quality of the meals</th><th>Items received</th><th>Issues reported</th><th>Actions taken</th><th>Remarks / reasons for variation</th><th>Challenges</th></tr></thead><tbody>
 @if($mealEntries->isNotEmpty()) @foreach($mealEntries as $entry)<tr><td>Welfare</td><td>{{ $entry['delivery_time']??'—' }}</td><td class="num">{{ $entry['preparers']??'—' }}</td><td class="num">{{ $entry['servers']??'—' }}</td><td>{{ $entry['meals']??'—' }}</td><td>{{ $entry['quality']??'—' }}</td><td>{{ $entry['items_received']??'—' }}</td><td>{{ $entry['issues']??'—' }}</td><td>{{ $entry['actions']??'—' }}</td><td>{{ $entry['remarks']??'—' }}</td><td>{{ $entry['challenges']??'—' }}</td></tr>@endforeach @else
 @forelse($welfare as $record)<tr><td>Welfare</td><td>{{ \Carbon\Carbon::parse($record->created_at)->format('H:i') }}</td><td class="num">—</td><td class="num">—</td><td>{{ $record->title ?: Str::headline($record->category) }}</td><td class="num">—</td><td>{{ $record->amount ? 'UGX '.number_format($record->amount) : '—' }}</td><td>{{ $record->details ?: '—' }}</td><td>{{ Str::headline($record->status) }}</td><td>—</td><td>—</td></tr>@empty<tr><td>Welfare</td><td colspan="10" class="empty">No welfare record was entered for this date.</td></tr>@endforelse
 @endif
 </tbody></table>

 <h4><span class="section-no">4</span>Daily Fleet Management</h4>
 <table class="wide"><thead><tr><th>Deliverable</th><th>Vehicle ID</th><th>Driver Name</th><th>Set-off point &amp; time</th><th>Number of people in the vehicle</th><th>Purpose</th><th>Mileage</th><th>Fuel consumption</th><th>Fuel receipt</th><th>Vehicle issues</th><th>Vehicle maintenance</th></tr></thead><tbody>
 @forelse($snapshot['fleet'] as $record)<tr><td>Fleet</td><td>{{ $record->vehicle_id }}</td><td>{{ $record->driver_name }}</td><td>{{ $record->pickup_point ?: '—' }}{{ $record->set_off_at ? ' · '.\Carbon\Carbon::parse($record->set_off_at)->format('H:i') : '' }}</td><td class="num">{{ $record->people_count ?? '—' }}</td><td>{{ $record->purpose }}</td><td class="num">@if($record->starting_odometer_km!==null && $record->ending_odometer_km!==null){{ number_format((float)$record->starting_odometer_km,1) }} → {{ number_format((float)$record->ending_odometer_km,1) }} km<br><small>{{ number_format((float)$record->ending_odometer_km-(float)$record->starting_odometer_km,1) }} km travelled</small>@else — @endif</td><td class="num">{{ $record->fuel_consumption_litres!==null ? number_format($record->fuel_consumption_litres,2).' litres' : '—' }}</td><td>{{ $record->fuel_receipt ? 'Uploaded' : '—' }}</td><td>{{ $record->vehicle_issues ?: '—' }}</td><td>{{ $record->maintenance ?: '—' }}</td></tr>@empty<tr><td>Fleet</td><td colspan="10" class="empty">No fleet movement was entered for this date.</td></tr>@endforelse
 </tbody></table>

 <h4><span class="section-no">5</span>Daily Construction Report</h4>
 <table><thead><tr><th>Deliverable</th><th>Project ID</th><th>Project timeline</th><th>Number of people on the project</th><th>Key contact person</th><th>Number of activities on the project</th><th>Tasks to be achieved</th><th>Status completed</th><th>Status pending</th><th>Challenges</th><th>PPE on site</th></tr></thead><tbody>
 @if($projectEntries->isNotEmpty()) @foreach($projectEntries as $entry)<tr><td>{{ $entry['deliverable']??'Construction' }}</td><td>{{ $entry['project_id']??'—' }}</td><td>{{ $entry['timeline']??'—' }}</td><td>{{ $entry['people']??'—' }}</td><td>{{ $entry['contact']??'—' }}</td><td>{{ $entry['activity_count']??'—' }}</td><td>{{ $entry['tasks']??'—' }}</td><td>{{ filled($entry['completed']??null)?$entry['completed'].'%':'—' }}</td><td>{{ filled($entry['pending']??null)?$entry['pending'].'%':'—' }}</td><td>{{ $entry['challenges']??'—' }}</td><td>{{ $entry['ppe']??'—' }}</td></tr>@endforeach @else
 <tr><td>Construction</td><td>—</td><td>—</td><td class="num">—</td><td>—</td><td class="num">—</td><td>{{ $sectionText('construction','activities') ?: '—' }}</td><td>—</td><td>—</td><td>{{ $sectionText('construction','challenges') ?: '—' }}</td><td>—</td></tr>
 @endif
 </tbody></table>

 <h4><span class="section-no">6</span>Ad Hoc Activities</h4>
 <table><thead><tr><th>Deliverable</th><th>Number of Ad Hoc activities</th><th>Type of Ad Hoc activities</th><th>Description of the activity</th><th>Reason for the Ad Hoc activities</th><th>Status of implementation</th><th>Remarks</th></tr></thead><tbody>
 @if($adHocEntries->isNotEmpty()) @foreach($adHocEntries as $entry)<tr><td>{{ $entry['deliverable']??'Ad hoc activity' }}</td><td>{{ $adHocEntries->count() }}</td><td>{{ $entry['type']??'—' }}</td><td>{{ $entry['description']??'—' }}</td><td>{{ $entry['reason']??'—' }}</td><td>{{ $entry['status']??'—' }}</td><td>{{ $entry['remarks']??'—' }}</td></tr>@endforeach @else
 <tr><td>CSR, client requests, emergencies, technical issues, meetings and disciplinary actions</td><td class="num">{{ $departmentRows->has('ad_hoc') ? 1 : 0 }}</td><td>{{ $sectionText('ad_hoc','summary') ?: '—' }}</td><td>{{ $sectionText('ad_hoc','activities') ?: '—' }}</td><td>{{ $sectionText('ad_hoc','recommendations') ?: '—' }}</td><td>{{ $departmentRows->has('ad_hoc') ? Str::headline($departmentRows->get('ad_hoc')->status) : '—' }}</td><td>{{ $sectionText('ad_hoc','challenges') ?: '—' }}</td></tr>
 @endif
 </tbody></table>

 @php
 $challenges=$submitted->pluck('challenges')->filter();
 $recommendations=$submitted->pluck('recommendations')->filter();
 $achievements=$submitted->pluck('activities')->filter();
 $observationValues=[
  'challenges'=>$managementObservations->get('challenges') ?: ($challenges->isNotEmpty() ? $challenges->join("\n") : ''),
  'emerging_issues'=>$managementObservations->get('emerging_issues') ?: $snapshot['records']->whereIn('type',['discipline','leave','incident'])->pluck('details')->filter()->join("\n"),
  'recommendations'=>$managementObservations->get('recommendations') ?: ($recommendations->isNotEmpty() ? $recommendations->join("\n") : ''),
  'achievements'=>$managementObservations->get('achievements') ?: ($achievements->isNotEmpty() ? $achievements->join("\n") : ''),
 ];
 @endphp
 @if($editableObservations??false)
 <form method="POST" action="{{ route('daily-reports.hr-observations') }}">@csrf<input type="hidden" name="report_date" value="{{ $date }}">
  <h4><span class="section-no">7</span>Challenges</h4><textarea name="challenges" class="legacy-hr__response" placeholder="Enter challenges">{{ old('challenges',$observationValues['challenges']) }}</textarea>
  <h4><span class="section-no">8</span>Emerging Issues</h4><textarea name="emerging_issues" class="legacy-hr__response" placeholder="Enter emerging issues">{{ old('emerging_issues',$observationValues['emerging_issues']) }}</textarea>
  <h4><span class="section-no">9</span>Recommendations / Proposed Actions</h4><textarea name="recommendations" class="legacy-hr__response" placeholder="Enter recommendations or proposed actions">{{ old('recommendations',$observationValues['recommendations']) }}</textarea>
  <h4><span class="section-no">10</span>Achievements</h4><textarea name="achievements" class="legacy-hr__response" placeholder="Enter achievements">{{ old('achievements',$observationValues['achievements']) }}</textarea>
  <button class="btn" style="margin-top:14px">Save report responses</button>
 </form>
 @else
  <h4><span class="section-no">7</span>Challenges</h4><div class="narrative issue">{{ $observationValues['challenges'] ?: 'No challenges were submitted.' }}</div>
  <h4><span class="section-no">8</span>Emerging Issues</h4><div class="narrative">{{ $observationValues['emerging_issues'] ?: 'No emerging issue was recorded.' }}</div>
  <h4><span class="section-no">9</span>Recommendations / Proposed Actions</h4><div class="narrative issue">{{ $observationValues['recommendations'] ?: 'No recommendations were submitted.' }}</div>
  <h4><span class="section-no">10</span>Achievements</h4><div class="narrative issue">{{ $observationValues['achievements'] ?: 'No achievements were submitted.' }}</div>
 @endif
</div></div></div>
