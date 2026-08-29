@extends('layouts.app')
@section('title','Unit Costs & Profitability')
@section('content')
<x-report-header eyebrow="Director's management report" title="Unit Costs & Profitability" description="Revenue, spending and accumulated lifecycle cost for each farm unit." :period="\Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')"><x-slot:actions><a class="btn btn--quiet" href="{{ route('sales.index',['screen'=>'reports']) }}">Revenue analysis</a><a class="btn" href="{{ route('finance.reports.statement',['report'=>'profit-loss','from'=>$from,'to'=>$to]) }}">Income statement</a></x-slot:actions></x-report-header>

<form class="card filters" method="GET">
    <div class="field"><label>From</label><input type="date" name="from" value="{{ $from }}" required></div>
    <div class="field"><label>To / costing date</label><input type="date" name="to" value="{{ $to }}" required></div>
    <div class="field"><label>Lifecycle detail</label><select name="unit"><option value="">Unit summary only</option>@foreach($unitOptions as $option)<option value="{{ $option }}" @selected($selectedUnit===$option)>{{ $option }}</option>@endforeach</select></div>
    <button class="btn">Calculate</button>
</form>

<div class="summary-grid">
    <div class="summary-card"><span>Revenue received</span><strong>UGX {{ number_format($totalRevenue) }}</strong><small>All finance receipts in the selected period</small></div>
    <div class="summary-card"><span>Money spent</span><strong>UGX {{ number_format($totalSpent) }}</strong><small>All payments in the selected period</small></div>
    <div class="summary-card"><span>Cash-basis result</span><strong>UGX {{ number_format($totalRevenue-$totalSpent) }}</strong><small>Revenue received less money paid</small></div>
    <div class="summary-card"><span>Not allocated to a farm unit</span><strong>UGX {{ number_format($unallocated) }}</strong><small>Finance, HR, executive or entries missing a cost centre</small></div>
</div>

<section class="card table-card">
    <div class="section-heading"><div><p class="card-label">Selected period</p><h3>Money by farm unit</h3></div></div>
    <table><thead><tr><th>Unit</th><th>Sales revenue</th><th>Direct spending</th><th>Result before shared costs</th><th></th></tr></thead><tbody>
    @foreach($units as $row)<tr><td><strong>{{ $row['unit'] }}</strong></td><td>UGX {{ number_format($row['revenue']) }}</td><td>UGX {{ number_format($row['spent']) }}</td><td>UGX {{ number_format($row['result']) }}</td><td><a href="{{ route('finance.reports.profitability',['from'=>$from,'to'=>$to,'unit'=>$row['unit']]) }}">View lifecycle costs</a></td></tr>@endforeach
    </tbody></table>
    <p class="muted">Unit spending comes from Finance entries carrying that cost centre. Sales are grouped by product category. Shared and unallocated costs are shown separately and are not silently assigned to a unit.</p>
</section>

@if($selectedUnit)
<section class="card table-card">
    <div class="section-heading"><div><p class="card-label">From acquisition through {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p><h3>{{ $selectedUnit }} lifecycle costs</h3></div></div>
    @if($selectedUnit==='Garden')
        <p class="muted">Garden costs are available in the unit summary. Per-animal costing does not apply to the Garden unit.</p>
    @else
    <table><thead><tr><th>Batch / group</th><th>Acquired</th><th>Initial</th><th>Remaining</th><th>Purchase</th><th>Feed</th><th>Health</th><th>Total cost</th><th>Cost per animal</th><th>Status</th></tr></thead><tbody>
    @forelse($lifeCycles as $row)<tr><td><strong>{{ $row['code'] }}</strong><br><span class="muted">{{ $row['description'] }}</span></td><td>{{ \Carbon\Carbon::parse($row['acquired_on'])->format('d M Y') }}</td><td>{{ number_format($row['initial']) }}</td><td>{{ number_format($row['remaining']) }}</td><td>UGX {{ number_format($row['acquisition']) }}</td><td>UGX {{ number_format($row['feed']) }}</td><td>UGX {{ number_format($row['health']) }}</td><td><strong>UGX {{ number_format($row['total']) }}</strong></td><td>UGX {{ number_format($row['per_animal']) }}</td><td>{{ Str::headline($row['stage']) }}</td></tr>
    @empty<tr><td colspan="10">No groups were acquired by the selected costing date.</td></tr>@endforelse
    </tbody></table>
    <p class="muted">Cost per animal uses the original number received, including free birds for poultry. It includes recorded acquisition, feed and health costs. Unit sales remain at unit level until a sale is linked to a specific batch or group.</p>
    @endif
</section>
@endif
@endsection
