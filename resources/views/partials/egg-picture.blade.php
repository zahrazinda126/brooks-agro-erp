{{-- Pictures for workers who may not read: a full tray of eggs, or single loose eggs. $kind = 'tray' | 'loose' --}}
@if(($kind ?? 'tray') === 'tray')
<svg class="egg-picture" viewBox="0 0 64 44" role="img" aria-label="Tray of eggs">
    <rect x="2" y="6" width="60" height="34" rx="5" fill="#e9d3b4" stroke="#9b6b3c" stroke-width="2"/>
    @foreach([12, 25, 38, 51] as $x)
        @foreach([16, 30] as $y)
            <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="5" ry="6" fill="#fffaf0" stroke="#9b6b3c" stroke-width="1.6"/>
        @endforeach
    @endforeach
</svg>
@else
<svg class="egg-picture" viewBox="0 0 44 44" role="img" aria-label="Single eggs">
    <ellipse cx="14" cy="24" rx="8" ry="10.5" fill="#fffaf0" stroke="#9b6b3c" stroke-width="2.2"/>
    <ellipse cx="31" cy="26" rx="7" ry="9" fill="#fffaf0" stroke="#9b6b3c" stroke-width="2.2"/>
</svg>
@endif
