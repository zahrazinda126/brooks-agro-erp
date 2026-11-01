{{-- The PA's daily report to the Director (the Chairman), laid out like his Word report: directives per responsible
     officer, poultry production per batch and house, projects / farm activities, and the Chairman's engagements.
     Everything comes from the system except the projects table and matters for the Chairman ($editable shows boxes). --}}
@pushOnce('styles')<style>
.legacy-hr{background:#fff;border:1px solid #aaa;padding:10px;color:#111;font:13px Arial,sans-serif}.legacy-hr__scroll{overflow-x:auto;padding-bottom:8px}.legacy-hr__sheet{min-width:0}.legacy-hr h4{font-size:14px;margin:20px 0 2px;text-transform:uppercase}.legacy-hr .officer{margin:0 0 5px;font-weight:700}.legacy-hr table{width:100%;min-width:760px;border-collapse:collapse;background:#fff}.legacy-hr th,.legacy-hr td{border:1px solid #222;padding:5px 6px;vertical-align:middle;color:#111}.legacy-hr th{background:#c6e0b4;text-align:center;font-weight:700}.legacy-hr .num{text-align:center;white-space:nowrap}.legacy-hr .total td{font-weight:700;background:#f3f3f3}.legacy-hr .narrative{padding:8px 3px;white-space:pre-line}.legacy-hr .issue{color:#b11;font-weight:600}.legacy-hr .empty{color:#8b8178;text-align:center}.legacy-hr .other-records{margin-top:26px;padding-top:6px;border-top:1px dashed #999}.legacy-hr__response{display:block;width:100%;min-height:80px;padding:10px;border:1px solid #222;background:#fff;color:#111;font:inherit;resize:vertical}.legacy-hr .cell-input{width:100%;min-height:32px;padding:4px 6px;border:1px solid #bbb;border-radius:4px;font:inherit}.legacy-hr .add-row{margin-top:6px}
</style>@endPushOnce
@php
    $editable = $editable ?? false;
    $byOfficer = $facts['byOfficer'] ?? [];
    $poultry = $facts['poultry'] ?? [];
    $engagements = $facts['engagements'] ?? [];
    $visitors = $facts['visitors'] ?? [];
    $money = $facts['money'] ?? ['approved' => 0, 'accounted' => 0, 'awaiting' => []];
    $build = $facts['construction'] ?? [];
    $activities = old('activities', $report->activities ?? []);
    if ($editable && count($activities) < 3) $activities = array_merge($activities, array_fill(0, 3 - count($activities), ['project' => '', 'activity' => '', 'status' => '']));
    // "CEO" reads as "CEO'S INDIVIDUAL DIRECTIVES REPORT"; other teams as "HR – INDIVIDUAL DIRECTIVES REPORT".
    $officerTitle = fn ($team) => mb_strtoupper($team).(preg_match('/^(ceo|director|chairman)$/i', $team) ? "'S" : ' –').' INDIVIDUAL DIRECTIVES REPORT';
@endphp
<div class="legacy-hr"><div class="legacy-hr__scroll"><div class="legacy-hr__sheet">
 @include('partials.report-letterhead', ['title' => "The P.A.'s Daily Report", 'date' => $date->format('jS M Y')])

 @forelse($byOfficer as $team => $rows)
  <h4>{{ $officerTitle($team) }}</h4>
  <p class="officer">Responsible Officer: {{ $team }}</p>
  <table><thead><tr><th style="width:44px">No.</th><th style="width:80px">Date</th><th>Chairman's Directive</th><th style="width:30%">Status / Action</th></tr></thead><tbody>
  @foreach($rows as $i => $d)<tr><td class="num">{{ $i + 1 }}</td><td class="num">{{ $d['date'] }}</td><td>{{ $d['directive'] }}</td><td>{{ $d['status'] }}</td></tr>@endforeach
  </tbody></table>
 @empty
  <h4>Individual Directives Report</h4>
  <table><tbody><tr><td class="empty">No open directives.</td></tr></tbody></table>
 @endforelse

 @forelse($poultry as $batch)
  <h4>Batch {{ $batch['batch'] }} – Poultry Production</h4>
  <table><thead><tr><th>Poultry House</th><th>Trays Produced</th><th>Extra Eggs</th><th>Damage</th></tr></thead><tbody>
  @foreach($batch['houses'] as $h)<tr><td class="num">{{ $h['house'] }}</td><td class="num">{{ $h['trays'] ?: '' }}</td><td class="num">{{ $h['extra'] ?: '' }}</td><td class="num">{{ $h['damage'] ?: '' }}</td></tr>@endforeach
  <tr class="total"><td class="num">TOTAL</td><td class="num">{{ collect($batch['houses'])->sum('trays') }}</td><td class="num">{{ collect($batch['houses'])->sum('extra') }}</td><td class="num">{{ collect($batch['houses'])->sum('damage') }}</td></tr>
  </tbody></table>
 @empty
  <h4>Poultry Production</h4>
  <table><tbody><tr><td class="empty">No eggs were recorded for this day.</td></tr></tbody></table>
 @endforelse

 <h4>Projects / Farm Activities</h4>
 <table><thead><tr><th style="width:44px">S/N</th><th style="width:20%">Project</th><th>Activity Done</th><th style="width:20%">Status</th></tr></thead><tbody id="pa-activities">
 @if($editable)
  @foreach($activities as $i => $a)<tr><td class="num">{{ $i + 1 }}</td><td><input class="cell-input" name="activities[{{ $i }}][project]" value="{{ $a['project'] ?? '' }}" list="pa-projects" placeholder="e.g. Kituntu"></td><td><input class="cell-input" name="activities[{{ $i }}][activity]" value="{{ $a['activity'] ?? '' }}" placeholder="e.g. Irrigation in the tomatoes"></td><td><input class="cell-input" name="activities[{{ $i }}][status]" value="{{ $a['status'] ?? '' }}" list="pa-statuses" placeholder="Done / On going"></td></tr>@endforeach
 @else
  @forelse($report->activities ?? [] as $i => $a)<tr><td class="num">{{ $i + 1 }}</td><td>{{ $a['project'] ?: '—' }}</td><td>{{ $a['activity'] }}</td><td class="num">{{ $a['status'] ?: '—' }}</td></tr>
  @empty<tr><td colspan="4" class="empty">{{ $report->did_today ?: 'No activities were listed.' }}</td></tr>@endforelse
 @endif
 </tbody></table>
 @if($editable)
  <datalist id="pa-projects">@foreach(['Kituntu', 'Poultry', 'Goats', 'Rabbits', 'Dogs', 'Garden', 'Construction'] as $p)<option value="{{ $p }}">@endforeach</datalist>
  <datalist id="pa-statuses">@foreach(['Done', 'On going', 'In progress', 'Pending', 'Not done'] as $st)<option value="{{ $st }}">@endforeach</datalist>
  <button type="button" class="btn btn--quiet btn--small add-row no-print" id="pa-add-activity">+ Add a row</button>
  <script>
  document.getElementById('pa-add-activity').addEventListener('click', function () {
      var body = document.getElementById('pa-activities'), i = body.rows.length, row = body.insertRow();
      row.innerHTML = '<td class="num">' + (i + 1) + '</td><td><input class="cell-input" name="activities[' + i + '][project]" list="pa-projects" placeholder="e.g. Kituntu"></td><td><input class="cell-input" name="activities[' + i + '][activity]" placeholder="Activity done"></td><td><input class="cell-input" name="activities[' + i + '][status]" list="pa-statuses" placeholder="Done / On going"></td>';
      row.querySelector('input').focus();
  });
  </script>
 @endif

 <h4>Chairman's Engagements</h4>
 <table><thead><tr><th style="width:44px">No.</th><th>Group / Person</th><th>Event</th><th>Reason</th><th>Location</th><th>Date</th><th>Contact</th><th>Time</th></tr></thead><tbody>
 @forelse($engagements as $i => $e)<tr><td class="num">{{ $i + 1 }}</td><td>{{ $e['who'] ?: '—' }}</td><td>{{ $e['event'] }}</td><td>{{ $e['reason'] ?: '—' }}</td><td>{{ $e['location'] ?: 'TBC' }}</td><td class="num">{{ $e['date'] }}</td><td class="num">{{ $e['contact'] ?: '' }}</td><td class="num">{{ $e['time'] }}</td></tr>
 @empty<tr><td colspan="8" class="empty">No engagements in the last week or the next two weeks. Add them under Director's Meetings.</td></tr>@endforelse
 </tbody></table>

 <h4>Matters for the Chairman</h4>
 @if($editable)
  <textarea name="issues" class="legacy-hr__response" maxlength="5000" aria-label="Matters for the Chairman" placeholder="Anything that needs the Chairman's decision or attention (optional)">{{ old('issues', $report->issues) }}</textarea>
 @else
  <div class="narrative issue">{{ $report->issues ?: 'None.' }}</div>
  @if($report->director_comment)<h4>Chairman's Comment</h4><div class="narrative">{{ $report->director_comment }}</div>@endif
 @endif

 <div class="other-records">
 <h4>Other records for the day</h4>
 <p class="officer">Visitors</p>
 <table><thead><tr><th>No.</th><th>Visitor</th><th>Organisation</th><th>Person visited</th><th>Purpose</th><th>Time in</th><th>Time out</th></tr></thead><tbody>
 @forelse($visitors as $i => $v)<tr><td class="num">{{ $i + 1 }}</td><td>{{ $v['name'] }}</td><td>{{ $v['organisation'] ?: '—' }}</td><td>{{ $v['visiting'] }}</td><td>{{ $v['purpose'] }}</td><td class="num">{{ $v['in'] ?: '—' }}</td><td class="num">{{ $v['out'] ?: 'Still on farm' }}</td></tr>
 @empty<tr><td colspan="7" class="empty">No visitors were recorded.</td></tr>@endforelse
 </tbody></table>
 <p class="officer" style="margin-top:12px">Approved money follow-up · approved UGX {{ number_format($money['approved']) }} · accounted for UGX {{ number_format($money['accounted']) }}</p>
 <table><thead><tr><th>No.</th><th>Purpose</th><th>Approved (UGX)</th><th>Accounted for (UGX)</th><th>Status</th></tr></thead><tbody>
 @forelse($money['awaiting'] as $i => $f)<tr><td class="num">{{ $i + 1 }}</td><td>{{ $f['purpose'] ?: '—' }}</td><td class="num">{{ number_format($f['approved']) }}</td><td class="num">{{ number_format($f['accounted']) }}</td><td class="num">{{ ucfirst($f['status']) }}</td></tr>
 @empty<tr><td colspan="5" class="empty">No approved money is waiting to be accounted for.</td></tr>@endforelse
 </tbody></table>
 @if($build)
 <p class="officer" style="margin-top:12px">Construction</p>
 <table><thead><tr><th>No.</th><th>Project</th><th>Builders on site</th><th>Materials issued (UGX)</th><th>Paid (UGX)</th><th>Owed to suppliers (UGX)</th></tr></thead><tbody>
 @foreach($build as $i => $p)<tr><td class="num">{{ $i + 1 }}</td><td>{{ $p['name'] }}</td><td class="num">{{ $p['builders'] }}</td><td class="num">{{ number_format($p['materials']) }}</td><td class="num">{{ number_format($p['paid']) }}</td><td class="num">{{ number_format($p['owed']) }}</td></tr>@endforeach
 </tbody></table>
 @endif
 </div>
</div></div></div>
