@extends('layouts.app')
@section('title',$config['name'].' Report')
@section('content')
<x-report-header eyebrow="Unit report" :title="$config['name'].' Report'" description="Operational activity, output, health, losses and costs recorded for this unit." :period="$from->format('d M Y').' – '.$to->format('d M Y')"><x-slot:actions><a class="btn btn--quiet" href="{{ route('operations.units.index',['unit'=>$unit]) }}">Unit overview</a><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button><a class="btn" href="{{ route('operations.units.export',['unit'=>$unit,'from'=>$from->toDateString(),'to'=>$to->toDateString()]) }}">Download Excel</a></x-slot:actions></x-report-header>
@php
    $section = isset($groups) ? (array_key_exists(request('section'), \App\Support\UnitReports::SECTIONS) ? request('section') : 'production') : null;
    $fromDate = $from->toDateString(); $toDate = $to->toDateString();
@endphp
<form class="card filter-row-card" method="GET">@if($section)<input type="hidden" name="section" value="{{ $section }}">@endif<div class="filter-row"><div class="field"><label for="ur-from">From</label><input id="ur-from" type="date" name="from" value="{{ $fromDate }}"></div><div class="field"><label for="ur-to">To</label><input id="ur-to" type="date" name="to" value="{{ $toDate }}"></div>@include('partials.unit-report-switch', ['part' => 'unit', 'unit' => $unit, 'section' => $section, 'from' => $fromDate, 'to' => $toDate])<div class="filter-row__actions"><button class="btn btn--small">Apply</button></div></div></form>
@include('partials.unit-report-switch', ['part' => 'tabs', 'unit' => $unit, 'section' => $section, 'from' => $fromDate, 'to' => $toDate])

@if(isset($groups))

@php $tables=[
 'feed'=>['title'=>'Feed records','rows'=>$feed,'date'=>'recorded_on','columns'=>['group_code'=>'Group','action'=>'Movement','feed_name'=>'Feed','quantity_kg'=>'Quantity (kg)','cost'=>'Cost (UGX)']],
 'health'=>['title'=>'Vaccination, treatment & health','rows'=>$health,'date'=>'recorded_on','columns'=>['group_code'=>'Group','event_type'=>'Event','condition_or_vaccine'=>'Condition / vaccine','medicine'=>'Medicine','animals_affected'=>'Animals','cost'=>'Cost (UGX)']],
 'mortality'=>['title'=>'Mortality records','rows'=>$mortality,'date'=>'occurred_on','columns'=>['group_code'=>'Group','quantity'=>'Number lost','suspected_cause'=>'Suspected cause','disposal_method'=>'Disposal']],
 'production'=>['title'=>'Production & births','rows'=>$production,'date'=>'recorded_on','columns'=>['group_code'=>'Group','production_type'=>'Output','quantity'=>'Quantity','measure_unit'=>'Unit','value'=>'Value (UGX)']],
]; @endphp
@php $table = $tables[$section]; @endphp<section class="card table-wrap"><table class="exec-table"><thead><tr><th>Date</th>@foreach($table['columns'] as $label)<th>{{ $label }}</th>@endforeach</tr></thead><tbody>@forelse($table['rows'] as $row)<tr><td>{{ \Carbon\Carbon::parse($row->{$table['date']})->format('d M Y') }}</td>@foreach($table['columns'] as $field=>$label)<td>{{ $row->{$field} ?? '—' }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($table['columns'])+1 }}">No {{ strtolower($table['title']) }} in this period.</td></tr>@endforelse</tbody></table></section>
@if($section === 'health')@include('partials.sick-bay-report', ['unit' => $unit, 'from' => $fromDate, 'to' => $toDate])@endif
@else
<section class="card table-wrap"><table class="exec-table"><thead><tr><th>Date</th><th>Record</th><th>Subject</th><th>Quantity</th><th>Value / Cost</th><th>Status</th></tr></thead><tbody>@forelse($records as $row)<tr><td>{{ $row->record_date->format('d M Y') }}</td><td>{{ $config['types'][$row->record_type] ?? $row->record_type }}</td><td>{{ $row->subject }}</td><td>{{ $row->quantity }} {{ $row->measure_unit }}</td><td>{{ $row->amount!==null?'UGX '.number_format($row->amount):'—' }}</td><td>{{ str_replace('_',' ',$row->status) }}</td></tr>@empty<tr><td colspan="6">No Garden records in this period.</td></tr>@endforelse</tbody></table></section>
@endif
@endsection
