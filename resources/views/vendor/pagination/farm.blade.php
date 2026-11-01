{{-- Compact page links used by every list: Previous, a few page numbers, Next. --}}
@if ($paginator->hasPages())
<nav class="farm-pager" role="navigation" aria-label="Pages">
    @if ($paginator->onFirstPage())<span class="farm-pager__step is-off">‹ Previous</span>@else<a class="farm-pager__step" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Previous</a>@endif
    @isset($elements)
        @foreach ($elements as $element)
            @if (is_string($element))<span class="farm-pager__gap">…</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())<span class="farm-pager__page is-current" aria-current="page">{{ $page }}</span>@else<a class="farm-pager__page" href="{{ $url }}">{{ $page }}</a>@endif
                @endforeach
            @endif
        @endforeach
    @endisset
    @if ($paginator->hasMorePages())<a class="farm-pager__step" href="{{ $paginator->nextPageUrl() }}" rel="next">Next ›</a>@else<span class="farm-pager__step is-off">Next ›</span>@endif
    @if (method_exists($paginator, 'total'))<span class="farm-pager__count">{{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}</span>@endif
</nav>
@endif
