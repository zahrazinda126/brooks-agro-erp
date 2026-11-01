@extends('layouts.app')
@section('title','P&L Performance Analysis')
@section('content')
<x-report-header eyebrow="Performance analysis" title="P&L Performance Analysis" description="Summary statement of comprehensive income against budget." :period="\Carbon\Carbon::parse($from)->format('d M Y').' – '.\Carbon\Carbon::parse($to)->format('d M Y')"><x-slot:actions><button class="btn" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>
<div class="form-actions"><a class="btn" href="{{ route('finance.reports.export',['report'=>'performance-analysis','from'=>$from,'to'=>$to]) }}">Download Excel</a></div>
@include('finance.reports._filter')
<section class="card performance-sheet">
    <div class="performance-title"><strong>Brooks Agro Farm Limited</strong><span>SUMMARY · {{ \Carbon\Carbon::parse($from)->format('d M Y') }} – {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</span></div>
    <table>
        <thead><tr><th>Summary Statement of<br>Comprehensive Income</th><th class="num">Actual<br><small>UGX</small></th><th class="num">Budget<br><small>UGX</small></th><th class="num">Variance<br><small>UGX</small></th><th>Management commentary</th></tr></thead>
        <tbody>
            @foreach($rows as $row)
                <tr class="performance-row--{{ $row['style'] ?? 'detail' }}">
                    <td>{{ $row['label'] }}</td>
                    @if(($row['style'] ?? '') === 'heading')
                        <td colspan="3"></td>
                    @else
                        <td class="num">{{ ($row['style'] ?? '') === 'percent' ? number_format($row['actual'],1).'%' : number_format($row['actual']) }}</td>
                        <td class="num">{{ ($row['style'] ?? '') === 'percent' ? number_format($row['budget'],1).'%' : number_format($row['budget']) }}</td>
                        <td class="num">{{ ($row['style'] ?? '') === 'percent' ? number_format($row['variance'],1).'%' : number_format($row['variance']) }}</td>
                    @endif
                    @if($loop->first)
                        <td class="performance-commentary" rowspan="{{ $rows->count() }}">
                            @if($canPrepareComment)
                                <form method="post" action="{{ route('finance.reports.performance-analysis.comment') }}">
                                    @csrf
                                    <input type="hidden" name="from" value="{{ $from }}">
                                    <input type="hidden" name="to" value="{{ $to }}">
                                    <label for="performance-comment">Finance Manager's commentary</label>
                                    <textarea id="performance-comment" name="comment" rows="12" placeholder="Summarise the period's results, major variances, causes and actions required.">{{ old('comment',$comment?->comment) }}</textarea>
                                    <button class="btn" type="submit">Save commentary</button>
                                </form>
                            @else
                                <div class="performance-commentary__text">{{ $comment?->comment ?: 'No management commentary has been prepared for this period.' }}</div>
                            @endif
                            @if($comment)<small class="muted">Prepared by {{ $comment->preparedBy?->displayName() ?? 'Finance' }} · {{ $comment->updated_at->format('d M Y, H:i') }}</small>@endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</section>
<p class="muted">Budget values come from approved, submitted and active budget lines whose dates overlap the selected period. The Finance Manager prepares one overall commentary for the selected reporting period.</p>
@pushOnce('styles')<style>.performance-sheet{max-width:1040px;padding:0;overflow:hidden;font-family:"Gill Sans MT","Segoe UI",Arial,sans-serif}.performance-title{display:grid;grid-template-columns:220px 1fr;background:var(--charcoal-800);color:#fff}.performance-title>*{padding:10px 14px}.performance-title strong{background:#9b4d00}.performance-sheet table{width:100%;border-collapse:collapse;font-size:12px}.performance-sheet th,.performance-sheet td{padding:7px 10px;border:1px solid #211d1a}.performance-sheet thead th{background:#9b9058;color:#fff}.performance-sheet thead th:first-child{background:#9b4d00;text-align:left}.performance-sheet th:last-child,.performance-sheet td:last-child{width:36%;text-align:left}.performance-sheet .num{text-align:right;font-variant-numeric:tabular-nums}.performance-row--subtotal td{font-weight:900;border-top:2px solid #111}.performance-row--total td{font-weight:900;border-top:2px solid #111;border-bottom:2px solid #111}.performance-row--heading td{padding-top:16px;font-weight:900}.performance-row--percent td{font-weight:800}.performance-commentary{vertical-align:top;background:#fffdf8}.performance-commentary form{display:grid;gap:8px}.performance-commentary label{font-weight:800}.performance-commentary textarea{width:100%;resize:vertical;min-height:180px;padding:8px;font:inherit;border:1px solid #9b9058;border-radius:4px}.performance-commentary .btn{width:auto;justify-self:start}.performance-commentary__text{white-space:pre-wrap;line-height:1.5;min-height:180px}.performance-commentary small{display:block;margin-top:10px}</style>@endPushOnce
@endsection
