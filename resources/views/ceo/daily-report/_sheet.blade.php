{{-- The CEO's daily report to the Director, laid out like her Word report. Production, feed, staffing,
     enterprises, sales and payables come from the system; the written parts are boxes while $editable. --}}
@pushOnce('styles')<style>
.ceo-sheet{background:#fff;border:1px solid #aaa;padding:10px;color:#111;font:13px Arial,sans-serif}.ceo-sheet__scroll{overflow-x:auto;padding-bottom:8px}.ceo-sheet h3{text-align:center;font-size:17px;margin:5px 0 4px;text-transform:uppercase}.ceo-sheet .ceo-sheet__sub{text-align:center;margin:0 0 14px;font-weight:700}.ceo-sheet h4{font-size:14px;margin:18px 0 5px}.ceo-sheet table{width:100%;min-width:760px;border-collapse:collapse;background:#fff}.ceo-sheet th,.ceo-sheet td{border:1px solid #222;padding:5px 6px;vertical-align:middle;color:#111}.ceo-sheet th{background:#c6e0b4;text-align:center;font-weight:700}.ceo-sheet .num{text-align:right;white-space:nowrap}.ceo-sheet .total td{font-weight:700;background:#f3f3f3}.ceo-sheet .narrative{padding:6px 3px;white-space:pre-line}.ceo-sheet .empty{color:#8b8178;text-align:center}.ceo-sheet .facts-line{margin:0 0 6px;padding:7px 10px;border:1px solid #222;background:#eef5e9}.ceo-sheet .facts-line strong{margin:0 18px 0 4px}.ceo-sheet__response{display:block;width:100%;min-height:80px;padding:10px;border:1px solid #222;background:#fff;color:#111;font:inherit;resize:vertical}.ceo-sheet .note{margin:6px 0 0;font-style:italic}
</style>@endPushOnce
@php
    $editable = $editable ?? false;
    $staff = $facts['staff'] ?? ['total' => 0, 'present' => 0];
    $poultry = collect($facts['poultry'] ?? []);
    $feed = $facts['feed'] ?? ['rows' => [], 'transport' => 0, 'delivered' => false, 'deliveries' => 0, 'week' => ''];
    $feedRows = collect($feed['rows']);
    $enterprises = collect($facts['enterprises'] ?? []);
    $sales = collect($facts['sales'] ?? []);
    $payables = collect($facts['payables'] ?? []);
    $year = $date->format('Y');
    $allEggs = (int) $poultry->sum('eggs');
    $box = function (string $name, string $label, bool $required = false) use ($report) {
        return '<textarea name="'.$name.'" class="ceo-sheet__response" maxlength="5000"'.($required ? ' required' : '').' aria-label="'.e($label).'" placeholder="'.e($label).'">'.e(old($name, $report->{$name})).'</textarea>';
    };
    $text = fn ($value, $empty) => '<div class="narrative">'.e($value ?: $empty).'</div>';
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.');
@endphp
<div class="ceo-sheet"><div class="ceo-sheet__scroll">
 @include('partials.report-letterhead', ['title' => "CEO's Daily Report", 'date' => $date->format('jS F Y')])

 <h4>1. Operational Highlights</h4>
 {!! $editable ? $box('highlights', 'What happened on the farm today', true) : $text($report->highlights, 'Nothing was written.') !!}

 <h4>2. Projects (e.g. Kituntu)</h4>
 {!! $editable ? $box('projects', 'Progress on projects such as Kituntu') : $text($report->projects, 'No project update.') !!}

 <h4>3. Human Resource &amp; Administration</h4>
 <p class="facts-line">Total staffing:<strong>{{ number_format($staff['total']) }}</strong>Present today:<strong>{{ number_format($staff['present']) }}</strong></p>
 {!! $editable ? $box('hr_notes', 'Staff matters, security guards, dogs unit…') : $text($report->hr_notes, '') !!}

 <h4>4. Poultry Production</h4>
 <table><thead><tr><th>Batch</th><th>Breed</th><th>Age</th><th>Opening stock</th><th>Mortality</th><th>Sold</th><th>Closing stock</th><th>Production</th></tr></thead><tbody>
 @forelse($poultry as $b)<tr><td>{{ $b['batch'] }}</td><td>{{ $b['breed'] }}</td><td>{{ $b['age'] }}</td><td class="num">{{ number_format($b['opening']) }}</td><td class="num">{{ $b['mortality'] ?: '' }}</td><td class="num">{{ $b['sold'] ?: '' }}</td><td class="num">{{ number_format($b['closing']) }}</td><td class="num">{{ $b['eggs'] ? $b['trays'].' Trays / '.$b['loose'].' Eggs' : '—' }}</td></tr>
 @empty<tr><td colspan="8" class="empty">No poultry batches on the farm.</td></tr>@endforelse
 @if($poultry->isNotEmpty())<tr class="total"><td colspan="3">Total</td><td class="num">{{ number_format($poultry->sum('opening')) }}</td><td class="num">{{ $poultry->sum('mortality') ?: '' }}</td><td class="num">{{ $poultry->sum('sold') ?: '' }}</td><td class="num">{{ number_format($poultry->sum('closing')) }}</td><td class="num">{{ $allEggs ? intdiv($allEggs, 30).' Trays / '.($allEggs % 30).' Eggs' : '—' }}</td></tr>@endif
 </tbody></table>

 <h4>5. Feed for this Week <small>({{ $feed['week'] }})</small></h4>
 <table><thead><tr><th>Item</th><th>Quantity (kg)</th><th>Unit price (UGX)</th><th>Total price (UGX)</th></tr></thead><tbody>
 @forelse($feedRows as $f)<tr><td>{{ $f['item'] }}</td><td class="num">{{ number_format($f['kg']) }}</td><td class="num">{{ number_format($f['price']) }}</td><td class="num">{{ number_format($f['total']) }}</td></tr>
 @empty<tr><td colspan="4" class="empty">No feed delivered this week yet.</td></tr>@endforelse
 @if($feedRows->isNotEmpty())
  <tr><td>Transport</td><td class="num">–</td><td></td><td class="num">{{ number_format($feed['transport']) }}</td></tr>
  <tr class="total"><td>Totals</td><td class="num">{{ number_format($feedRows->sum('kg')) }}</td><td></td><td class="num">{{ number_format($feedRows->sum('total') + $feed['transport']) }}</td></tr>
 @endif
 </tbody></table>
 @if($feed['deliveries'])<p class="note">{{ $feed['delivered'] ? 'All feeds for the week were delivered and weighed at the farm.' : 'Some feed for the week has not yet been weighed at the farm.' }}</p>@endif

 <h4>6. Other Enterprises</h4>
 @if($enterprises->isNotEmpty())<p class="facts-line">@foreach($enterprises as $u){{ $u['unit'] }}:<strong>{{ number_format($u['animals']) }}</strong>@endforeach</p>@endif
 {!! $editable ? $box('enterprise_notes', 'Goats, rabbits, dogs, garden…') : $text($report->enterprise_notes, $enterprises->isEmpty() ? 'No update.' : '') !!}

 <h4>7. Finance &amp; Sales</h4>
 <table><thead><tr><th>Source</th><th>Quantity</th><th>Amount (UGX)</th></tr></thead><tbody>
 @forelse($sales as $s)<tr><td>{{ $s['item'] }}</td><td class="num">{{ $qty($s['quantity']) }} {{ \Illuminate\Support\Str::plural(ucfirst($s['unit']), (int) ceil($s['quantity'])) }}</td><td class="num">{{ number_format($s['amount']) }}</td></tr>
 @empty<tr><td colspan="3" class="empty">No sales were recorded.</td></tr>@endforelse
 @if($sales->isNotEmpty())<tr class="total"><td colspan="2">TOTAL</td><td class="num">{{ number_format($sales->sum('amount')) }}</td></tr>@endif
 </tbody></table>

 <h4>8. Outstanding Payables</h4>
 <table><thead><tr><th>Supplier</th><th>Service</th><th>Debts before {{ $year }}</th><th>{{ $year }} debts as at {{ $date->format('jS F') }}</th><th>Paid today</th><th>Total owed</th></tr></thead><tbody>
 @forelse($payables as $p)<tr><td>{{ $p['supplier'] }}</td><td>{{ $p['service'] }}</td><td class="num">{{ $p['older'] ? number_format($p['older']) : '–' }}</td><td class="num">{{ $p['current'] ? number_format($p['current']) : '–' }}</td><td class="num">{{ $p['paid_today'] ? number_format($p['paid_today']) : '–' }}</td><td class="num">{{ number_format($p['total']) }}</td></tr>
 @empty<tr><td colspan="6" class="empty">Nothing is owed to suppliers.</td></tr>@endforelse
 @if($payables->isNotEmpty())<tr class="total"><td colspan="2">Total</td><td class="num">{{ number_format($payables->sum('older')) }}</td><td class="num">{{ number_format($payables->sum('current')) }}</td><td class="num">{{ number_format($payables->sum('paid_today')) }}</td><td class="num">{{ number_format($payables->sum('total')) }}</td></tr>@endif
 </tbody></table>

 @if(! $editable && $report->director_comment)<h4>Director's Comment</h4><div class="narrative">{{ $report->director_comment }}</div>@endif
</div></div>
