{{--
    Icon sprite.

    Drawn inline as SVG symbols rather than loaded from an icon font.
    Font Awesome would be another network request that fails on a weak
    connection in a shed — and it would download several hundred glyphs
    to use twenty-nine. These ship with the page and cost nothing.

    Referenced with <use href="#i-name">. Stroke-based so a single
    definition works on both the dark sidebar and light content.
--}}
<svg xmlns="http://www.w3.org/2000/svg" style="display:none" aria-hidden="true" focusable="false">
    <defs>
        <g id="s" fill="none" stroke="currentColor" stroke-width="1.8"
           stroke-linecap="round" stroke-linejoin="round"></g>
    </defs>

    <symbol id="i-home" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/><path d="M9.5 20v-6h5v6"/>
    </symbol>

    <symbol id="i-clipboard" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M9 4h6v3H9z"/><path d="M7 5.5H5.5A1.5 1.5 0 0 0 4 7v12.5A1.5 1.5 0 0 0 5.5 21h13a1.5 1.5 0 0 0 1.5-1.5V7a1.5 1.5 0 0 0-1.5-1.5H17"/><path d="m8.5 13 2 2 4-4"/>
    </symbol>

    <symbol id="i-egg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3c3.3 0 6 4.6 6 9a6 6 0 0 1-12 0c0-4.4 2.7-9 6-9Z"/>
    </symbol>

    <symbol id="i-paw" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="7" cy="9" rx="1.9" ry="2.4"/><ellipse cx="12" cy="6.6" rx="1.9" ry="2.4"/>
        <ellipse cx="17" cy="9" rx="1.9" ry="2.4"/>
        <path d="M12 12c2.6 0 4.7 1.9 4.7 4.2 0 1.6-1.2 2.6-2.7 2.6-1 0-1.4-.5-2-.5s-1 .5-2 .5c-1.5 0-2.7-1-2.7-2.6C7.3 13.9 9.4 12 12 12Z"/>
    </symbol>

    <symbol id="i-vet" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 12h3l2-4 3 8 2.5-5 1.5 3h6"/>
    </symbol>

    <symbol id="i-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 3 5 6v6c0 4.2 2.9 7.6 7 9 4.1-1.4 7-4.8 7-9V6l-7-3Z"/>
    </symbol>

    <symbol id="i-syringe" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="m14 4 6 6"/><path d="m17 7-9.5 9.5L4 20l3.5-3.5L17 7Z"/><path d="m9 12 3 3"/><path d="m12 9 3 3"/>
    </symbol>

    <symbol id="i-dna" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M7 3c0 5 10 5 10 10S7 18 7 21"/><path d="M17 3c0 5-10 5-10 10s10 5 10 8"/>
        <path d="M8.5 7h7M8.5 17h7"/>
    </symbol>

    <symbol id="i-factory" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 21V10l5 3.5V10l5 3.5V10l5 3.5V6h3v15H3Z"/><path d="M7 17h1.5M12 17h1.5M17 17h1.5"/>
    </symbol>

    <symbol id="i-grain" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 21V9"/><path d="M12 9c0-3 2-5 4.5-6C17 6 15 9 12 9Z"/><path d="M12 9c0-3-2-5-4.5-6C7 6 9 9 12 9Z"/>
        <path d="M12 15c0-2.6 1.8-4.4 4-5 .4 2.6-1.4 5-4 5Z"/><path d="M12 15c0-2.6-1.8-4.4-4-5-.4 2.6 1.4 5 4 5Z"/>
    </symbol>

    <symbol id="i-badge" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="8.5"/><path d="m8.5 12 2.5 2.5L16 10"/>
    </symbol>

    <symbol id="i-wrench" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M15.5 3.5a5 5 0 0 0-4.7 6.6L3.6 17.3a2 2 0 0 0 2.8 2.8l7.2-7.2a5 5 0 0 0 6.3-6.2l-3 3-2.6-2.6 3-3a5 5 0 0 0-1.8-.6Z"/>
    </symbol>

    <symbol id="i-truck" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2 6h11v11H2z"/><path d="M13 10h4.5l3.5 3.5V17H13z"/>
        <circle cx="7" cy="18.5" r="1.8"/><circle cx="17" cy="18.5" r="1.8"/>
    </symbol>

    <symbol id="i-box" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5v-9Z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>
    </symbol>

    <symbol id="i-cart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 4h2.2l2.3 11h9.6l2.4-8H6"/><circle cx="9" cy="19" r="1.6"/><circle cx="17" cy="19" r="1.6"/>
    </symbol>

    <symbol id="i-receipt" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 3h14v18l-2.3-1.6-2.4 1.6-2.3-1.6L9.7 21l-2.4-1.6L5 21V3Z"/><path d="M9 8h6M9 12h6"/>
    </symbol>

    <symbol id="i-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/>
        <path d="M16 5.5a3.2 3.2 0 0 1 0 6.2"/><path d="M17.5 14.8c2.1.6 3.5 2.4 3.5 5.2"/>
    </symbol>

    <symbol id="i-coins" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <ellipse cx="9" cy="6.5" rx="6" ry="2.8"/><path d="M3 6.5v5c0 1.5 2.7 2.8 6 2.8s6-1.3 6-2.8v-5"/>
        <path d="M15 10.2c2.5.3 4.5 1.4 4.5 2.8v5c0 1.5-2.7 2.8-6 2.8-2.4 0-4.5-.7-5.4-1.7"/>
    </symbol>

    <symbol id="i-cash" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2.5" y="6" width="19" height="12" rx="1.6"/><circle cx="12" cy="12" r="2.6"/>
        <path d="M6 9.5v5M18 9.5v5"/>
    </symbol>

    <symbol id="i-ledger" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M5 3h13a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H5a2 2 0 0 1 0-4h13"/><path d="M9 7h6M9 11h6"/>
    </symbol>

    <symbol id="i-file" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M13 3H7a1.5 1.5 0 0 0-1.5 1.5v15A1.5 1.5 0 0 0 7 21h10a1.5 1.5 0 0 0 1.5-1.5V8.5L13 3Z"/>
        <path d="M13 3v5.5h5.5"/><path d="M8.5 13h7M8.5 16.5h5"/>
    </symbol>

    <symbol id="i-chart" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 20V4"/><path d="M4 20h16"/><path d="M8 17v-5M12.5 17V8M17 17v-7"/>
    </symbol>

    <symbol id="i-cpu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="6.5" y="6.5" width="11" height="11" rx="1.6"/><rect x="10" y="10" width="4" height="4"/>
        <path d="M10 3v3.5M14 3v3.5M10 17.5V21M14 17.5V21M3 10h3.5M3 14h3.5M17.5 10H21M17.5 14H21"/>
    </symbol>

    <symbol id="i-gear" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="3"/>
        <path d="M12 2.5v2.6M12 18.9v2.6M4.9 4.9l1.9 1.9M17.2 17.2l1.9 1.9M2.5 12h2.6M18.9 12h2.6M4.9 19.1l1.9-1.9M17.2 6.8l1.9-1.9"/>
    </symbol>

    <symbol id="i-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 16V10a6 6 0 1 0-12 0v6l-1.5 2.5h15L18 16Z"/><path d="M10 19.5a2 2 0 0 0 4 0"/>
    </symbol>

    <symbol id="i-message" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 5H4a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3v4l4.5-4H20a1 1 0 0 0 1-1V6a1 1 0 0 0-1-1Z"/>
    </symbol>

    <symbol id="i-lock" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="4.5" y="10" width="15" height="10.5" rx="1.6"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>
    </symbol>

    <symbol id="i-calendar" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3.5" y="5" width="17" height="16" rx="1.6"/><path d="M3.5 10h17M8 3v4M16 3v4"/>
        <path d="m9.5 15 1.8 1.8L15 13"/>
    </symbol>

    <symbol id="i-cap" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 4 2.5 8.5 12 13l9.5-4.5L12 4Z"/><path d="M6.5 10.8V16c0 1.5 2.5 3 5.5 3s5.5-1.5 5.5-3v-5.2"/>
    </symbol>

    <symbol id="i-check-circle" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="8.5"/><path d="m8.3 12.2 2.6 2.6 4.8-5"/>
    </symbol>

    <symbol id="i-dot" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="3.2"/>
    </symbol>
</svg>
