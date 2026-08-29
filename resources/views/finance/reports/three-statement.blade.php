@extends('layouts.app')
@section('title','Three-Statement Model')
@section('content')
<x-report-header eyebrow="Finance & management" title="Three-Statement Model" description="Income Statement, Balance Sheet and Cash Flow in the workbook’s July–June format." :period="'July '.$year.' – June '.($year+1)"><x-slot:actions><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button><a class="btn" href="{{ route('finance.reports.export',['report'=>'three-statement','year'=>$year]) }}">Download Excel</a></x-slot:actions></x-report-header>
<form class="card filters"><div class="field"><label>Financial year beginning</label><input type="number" name="year" min="2000" max="2100" value="{{ $year }}"></div><div class="field field--actions"><button class="btn">Run model</button></div></form>
<p class="model-scroll-hint">Scroll sideways to view February through June and the annual total.</p>
<div class="three-statement-scroll" tabindex="0" aria-label="Three-statement model; scroll horizontally for all months">
<table><thead><tr><th>Account</th>@foreach($model['columns'] as $column)<th class="num">{{ $column }}</th>@endforeach<th class="num">Annual total</th></tr></thead><tbody>
@php $section = null; @endphp
@foreach($model['rows'] as $row)
    @if($section !== $row['section']) @php $section = $row['section']; @endphp <tr class="statement-section"><th colspan="14">{{ $section }}</th></tr> @endif
    <tr class="statement-row statement-row--{{ $row['style'] }}"><td>{{ $row['label'] }}</td>@foreach($row['values'] as $value)<td class="num">{{ $value ? number_format($value) : '—' }}</td>@endforeach<td class="num"><strong>{{ number_format($row['total']) }}</strong></td></tr>
@endforeach
</tbody></table></div>
<p class="muted">The structure matches the management workbook. Zero rows indicate that the supporting loan, lease, inventory, tax, depreciation or equity ledger has not yet been imported. Shareholder funds currently acts as the disclosed Balance Sheet balancing figure until verified opening equity is loaded.</p>
@endsection
