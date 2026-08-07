@extends('layouts.app')
@section('title','Three-Statement Model')
@section('content')
<div class="page-head"><div><h2>Three-Statement Model</h2><p>Income Statement, Balance Sheet and Cash Flow in the workbook’s July–June format.</p></div><a class="btn" href="{{ route('finance.reports.export',['report'=>'three-statement','year'=>$year]) }}">Export CSV</a></div>
<form class="card filters"><div class="field"><label>Financial year beginning</label><input type="number" name="year" min="2000" max="2100" value="{{ $year }}"></div><div class="field field--actions"><button class="btn">Run model</button></div></form>
<div class="card table-card"><table><thead><tr><th>Account</th>@foreach($model['columns'] as $column)<th class="num">{{ $column }}</th>@endforeach<th class="num">Annual total</th></tr></thead><tbody>@php($section=null)@foreach($model['rows'] as $row)@if($section!==$row['section'])@php($section=$row['section'])<tr><th colspan="14">{{ $section }}</th></tr>@endif<tr><td>{{ $row['label'] }}</td>@foreach($row['values'] as $value)<td class="num">{{ $value ? number_format($value) : '—' }}</td>@endforeach<td class="num"><strong>{{ number_format($row['total']) }}</strong></td></tr>@endforeach</tbody></table></div>
<p class="muted">The structure matches the management workbook. Historical Balance Sheet columns require imported opening balances and dated ledger balances; until then, current registered fixed assets are shown only in the annual/current total rather than repeated as false monthly balances.</p>
@endsection
