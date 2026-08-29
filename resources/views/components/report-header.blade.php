@props(['eyebrow'=>'Management report','title','description'=>null,'period'=>null,'status'=>null])
<header class="standard-report-head">
 <div class="standard-report-head__brand"><img src="{{ asset('images/logo.png') }}" alt="Brooks Agro Farm"><div><strong>BROOKS AGRO FARM</strong><small>Management Information System</small></div></div>
 <div class="standard-report-head__main"><p class="eyebrow">{{ $eyebrow }}</p><h1>{{ $title }}</h1>@if($description)<p>{{ $description }}</p>@endif<div class="standard-report-head__meta">@if($period)<span>Reporting period: <strong>{{ $period }}</strong></span>@endif<span>Generated: <strong>{{ now()->format('d M Y, H:i') }}</strong></span><span>Prepared for: <strong>{{ auth()->user()->displayName() }}</strong></span>@if($status)<span class="status-pill">{{ $status }}</span>@endif</div></div>
 @isset($actions)<div class="standard-report-head__actions no-print">{{ $actions }}</div>@endisset
</header>
