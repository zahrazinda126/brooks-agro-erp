{{-- Report letterhead in the Farm Hub format: logo, farm name, address, rule,
     title in capitals, scope and period, and who generated it. Shown when printing. --}}
@php
    $farm = config('farm');
    $who = auth()->user();
    // @include shares the calling page's variables; only plain text is printed here.
    $scope = isset($scope) && is_scalar($scope) ? $scope : null;
    $period = isset($period) && is_scalar($period) ? $period : null;
    $title = is_scalar($title ?? null) ? $title : 'Report';
@endphp
<div class="print-letterhead {{ $class ?? '' }}">
    <div class="print-letterhead__brand">
        @if (file_exists(public_path('images/logo-round.png')))<img src="{{ asset('images/logo-round.png') }}" alt="">@endif
        <strong>{{ $farm['name'] }}</strong>
        <small>{{ $farm['address'] }} &nbsp;·&nbsp; Tel: {{ $farm['phone'] }}</small>
    </div>
    <h1 class="print-letterhead__title">{{ $title }}</h1>
    <p class="print-letterhead__scope">
        @if (! empty($scope)){{ $scope }} &nbsp;·&nbsp; @endif
        @if (! empty($period))Period: {{ $period }} &nbsp;·&nbsp; @endif
        Currency: {{ $farm['currency'] }}
    </p>
    <p class="print-letterhead__scope">
        Generated: {{ now()->format('d F Y, H:i') }} EAT
        @if ($who) &nbsp;·&nbsp; By: {{ $who->displayName() }} ({{ $who->roleSummary() }})@endif
    </p>
</div>
