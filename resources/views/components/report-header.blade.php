@props(['eyebrow'=>'Management report','title','description'=>null,'period'=>null,'status'=>null])
{{-- Report heading. On paper it prints as the Farm Hub letterhead (partials/print-letterhead). --}}
<header class="standard-report-head">
 @include('partials.print-letterhead', ['title' => $title, 'scope' => $description, 'period' => $period, 'class' => 'print-letterhead--report'])
 @if($status)<p class="standard-report-head__status"><span class="status-pill">{{ $status }}</span></p>@endif
 @isset($actions)<div class="standard-report-head__actions no-print">{{ $actions }}</div>@endisset
</header>
