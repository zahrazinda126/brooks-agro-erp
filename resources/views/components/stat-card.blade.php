{{-- Summary figure used across dashboards and reports: <x-stat-card label="Funded">@money($amount)</x-stat-card> --}}
@props(['label'])
<div {{ $attributes->merge(['class' => 'summary-card']) }}><span>{{ $label }}</span><strong>{{ $slot }}</strong></div>
