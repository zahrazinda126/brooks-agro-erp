@php
$definitions = match($departmentCode.'.'.$unitCode) {
 'hr.attendance' => ['rows'=>6,'key'=>'attendance','title'=>'Attendance exceptions and leave cover','fields'=>['staff_no'=>'Worker ID number','attendance_status'=>'Attendance status','leave_type'=>'Leave type','reliever'=>'Reliever','leave_start'=>'Leave start date','leave_end'=>'Leave end date','remarks'=>'Remarks']],
 'hr.security' => ['rows'=>4,'key'=>'security','title'=>'Security deployment','fields'=>['walkie_talkie'=>'Walkie-talkie No.','name'=>'Guard name','shift'=>'Shift','station'=>'Station','attendance'=>'Attendance','reporting_time'=>'Reporting time','challenge'=>'Challenge','remarks'=>'Remarks / reason for variation']],
 'hr.welfare' => ['rows'=>3,'key'=>'meals','title'=>'Meal and welfare deliveries','fields'=>['delivery_time'=>'Food delivery time','preparers'=>'People preparing','servers'=>'People serving','meals'=>'Meals served','quality'=>'Meal quality','items_received'=>'Items received','issues'=>'Issues reported','actions'=>'Actions taken','remarks'=>'Remarks / variation','challenges'=>'Challenges']],
 'hr.construction' => ['rows'=>3,'key'=>'projects','title'=>'Construction projects','fields'=>['deliverable'=>'Deliverable','project_id'=>'Project ID','timeline'=>'Project timeline','people'=>'People on project','contact'=>'Key contact person','activity_count'=>'Number of activities','tasks'=>'Tasks to achieve','completed'=>'Completed %','pending'=>'Pending %','challenges'=>'Challenges','ppe'=>'PPE on site']],
 'hr.ad_hoc' => ['rows'=>3,'key'=>'activities','title'=>'Ad hoc activities','fields'=>['deliverable'=>'Deliverable','type'=>'Activity type','description'=>'Description','reason'=>'Reason','status'=>'Implementation status','remarks'=>'Remarks']],
 default => null,
};
@endphp
@if($definitions)
<fieldset class="report-detail-fields"><legend>{{ $definitions['title'] }}</legend><p class="field-hint">Fill only the rows used today. These values feed the departmental report columns directly.</p>
 @for($i=0;$i<$definitions['rows'];$i++)
 <div class="report-detail-row"><strong>Entry {{ $i+1 }}</strong><div class="form-grid">
  @foreach($definitions['fields'] as $name=>$label) @php($value=old("details.{$definitions['key']}.$i.$name",data_get($details,"{$definitions['key']}.$i.$name")))
  <div class="field"><label>{{ $label }}</label>@if(Str::contains($name,'time'))<input type="time" name="details[{{ $definitions['key'] }}][{{ $i }}][{{ $name }}]" value="{{ $value }}">@elseif(in_array($name,['leave_start','leave_end'],true))<input type="date" name="details[{{ $definitions['key'] }}][{{ $i }}][{{ $name }}]" value="{{ $value }}">@elseif(in_array($name,['preparers','servers','people','activity_count','completed','pending'],true))<input type="number" min="0" @if(in_array($name,['completed','pending'],true)) max="100" @endif name="details[{{ $definitions['key'] }}][{{ $i }}][{{ $name }}]" value="{{ $value }}">@else<input name="details[{{ $definitions['key'] }}][{{ $i }}][{{ $name }}]" value="{{ $value }}">@endif</div>
  @endforeach
 </div></div>
 @endfor
</fieldset>
<style>.report-detail-fields{margin:1rem 0;padding:1rem;border:1px solid #e5c9aa;border-radius:14px}.report-detail-fields legend{font-weight:800;padding:0 .5rem}.report-detail-row{padding:1rem 0;border-top:1px solid #eee}.report-detail-row:first-of-type{border-top:0}</style>
@endif
