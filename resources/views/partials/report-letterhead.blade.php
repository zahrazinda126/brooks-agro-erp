{{-- Letterhead for the daily report templates, in the Farm Hub report format:
     logo, farm name, address and phone, a rule, then the report title and date. --}}
@php($farm = config('farm'))
<div class="report-letterhead">
    @if (file_exists(public_path('images/logo-round.png')))<img src="{{ asset('images/logo-round.png') }}" alt="Brooks Agro Farm">@endif
    <strong>{{ $farm['name'] }}</strong>
    <small class="report-letterhead__address">{{ $farm['address'] }} &nbsp;·&nbsp; Tel: {{ $farm['phone'] }}</small>
    <span class="report-letterhead__title">{{ $title }}</span>
    @isset($date)<small class="report-letterhead__date">Date: {{ $date }}</small>@endisset
</div>
@pushOnce('styles')<style>
.report-letterhead{display:flex;flex-direction:column;align-items:center;gap:2px;margin:4px 0 16px;text-align:center}
.report-letterhead img{width:64px;height:64px;object-fit:contain;margin-bottom:4px}
.report-letterhead strong{font:700 1.1rem/1.2 "Inter",Arial,sans-serif;letter-spacing:.02em;color:#111}
.report-letterhead__address{width:100%;padding-bottom:8px;border-bottom:1px solid #d6cfcd;font-size:.78rem;color:#444}
.report-letterhead__title{margin-top:10px;font:700 1.05rem/1.2 "Inter",Arial,sans-serif;letter-spacing:.02em;text-transform:uppercase;color:#111}
.report-letterhead__date{font-size:.8rem;font-weight:600;color:#333}
@media print{.report-letterhead img{width:17mm;height:17mm}.report-letterhead strong{font-size:15pt}.report-letterhead__address{font-size:8pt}.report-letterhead__title{font-size:14pt}}
</style>@endPushOnce
