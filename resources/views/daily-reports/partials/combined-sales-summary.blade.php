@php
$formatEggs=function($eggs,$perTray){$trays=intdiv((int)round($eggs),max(1,$perTray));$loose=(int)round($eggs)%max(1,$perTray);return $trays.'T'.($loose?'/'.$loose.'E':'');};
$payments=$snapshot['payments'];
$salesTotal=(float)$snapshot['sales']->sum('total');
$paidBy=function(array $methods) use($payments){return (float)$payments->filter(fn($p)=>in_array(Str::lower($p->payment_method),$methods,true))->sum('amount');};
$paymentRows=collect([
 ['label'=>'Customer prepayment credit','amount'=>$paidBy(['prepayment'])],
 ['label'=>'Credit outstanding','amount'=>(float)$snapshot['sales']->sum('balance')],
 ['label'=>'Cash','amount'=>$paidBy(['cash'])],
 ['label'=>'Bank','amount'=>$paidBy(['bank','bank_transfer','cheque'])],
 ['label'=>'MTN Merchant','amount'=>$paidBy(['mtn_merchant','mobile_money'])],
 ['label'=>'Airtel Merchant','amount'=>$paidBy(['airtel_merchant'])],
 ['label'=>'Agent','amount'=>$paidBy(['agent'])],
]);
@endphp
<section class="daily-combined-sheet" aria-label="Daily inventory and sales summary">
 <div class="daily-combined-sheet__scroll">
  <h2>SUMMARY REPORT FOR {{ \Carbon\Carbon::parse($date)->format('jS M Y') }}</h2>
  <table><thead><tr><th>ITEMS</th>@foreach($snapshot['egg_inventory'] as $grade)<th>{{ Str::upper($grade->name) }}</th>@endforeach</tr></thead><tbody>
   <tr><th>Opening Inventory</th>@foreach($snapshot['egg_inventory'] as $grade)<td>{{ $formatEggs($grade->opening,$grade->eggs_per_tray) }}</td>@endforeach</tr>
   <tr><th>Trays received</th>@foreach($snapshot['egg_inventory'] as $grade)<td>{{ $formatEggs($grade->received,$grade->eggs_per_tray) }}</td>@endforeach</tr>
   <tr><th>Closing Inventory</th>@foreach($snapshot['egg_inventory'] as $grade)<td>{{ $formatEggs($grade->closing,$grade->eggs_per_tray) }}</td>@endforeach</tr>
  </tbody></table>
  @if(in_array($mode,['sales','finance'],true))
  <table class="daily-combined-sheet__section"><thead><tr><th>SALES SUMMARY</th><th>QUANTITY</th><th>UNIT PRICE</th><th>AMOUNT</th></tr></thead><tbody>
   @forelse($snapshot['sale_lines'] as $line)<tr><td>{{ Str::upper($line->description) }}</td><td class="num">{{ number_format($line->quantity,2) }} {{ $line->unit }}</td><td class="num">{{ number_format($line->unit_price) }}</td><td class="num">{{ number_format($line->line_total) }}</td></tr>@empty<tr><td colspan="4">No sales recorded for this date.</td></tr>@endforelse
   <tr class="total"><th>TOTAL SALES</th><td class="num">{{ number_format($snapshot['sale_lines']->sum('quantity'),2) }}</td><td></td><td class="num">{{ number_format($salesTotal) }}</td></tr>
  </tbody></table>
  @endif
  @if($mode==='finance')
  <table class="daily-combined-sheet__payments"><tbody>@foreach($paymentRows as $row)<tr><th>{{ Str::upper($row['label']) }}</th><td class="num">{{ number_format($row['amount']) }}</td></tr>@endforeach<tr class="total"><th>TOTAL SALES</th><td class="num">{{ number_format($salesTotal) }}</td></tr></tbody></table>
  @endif
 </div>
 <p class="daily-combined-sheet__source">{{ $mode==='production' ? 'Production view: physical egg inventory only.' : ($mode==='sales' ? 'Sales view: physical inventory reference and sales entries.' : 'Finance view: complete stock, sales, and payment reconciliation.') }}</p>
</section>
@pushOnce('styles')<style>
.daily-combined-sheet{margin:0 0 20px;padding:12px;background:#fff;border:1px solid #9e958d;color:#111;font:13px Arial,sans-serif}.daily-combined-sheet__scroll{overflow-x:auto}.daily-combined-sheet h2{margin:2px 0 10px;text-align:center;font-size:16px}.daily-combined-sheet table{width:100%;min-width:760px;border-collapse:collapse}.daily-combined-sheet th,.daily-combined-sheet td{border:1px solid #222;padding:6px 8px;text-align:left}.daily-combined-sheet .num{text-align:right;white-space:nowrap}.daily-combined-sheet .total th,.daily-combined-sheet .total td{font-weight:800}.daily-combined-sheet__section,.daily-combined-sheet__payments{margin-top:10px}.daily-combined-sheet__payments{width:min(620px,100%)!important}.daily-combined-sheet__source{margin:9px 2px 0;color:#725c4c;font:12px var(--font-sans,Arial,sans-serif)}
</style>@endPushOnce
