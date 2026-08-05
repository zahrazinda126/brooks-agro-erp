<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#8B1E2D">
    <title>@yield('title', 'Brooks Agro')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
</head>
<body>

@include('partials.icons')

@php
    /*
     | Workers get no sidebar at all.
     |
     | A menu is for choosing between places. A worker has one place — their
     | flocks — and gets there by signing in. Filtering eighty screens down
     | to twelve still leaves a menu they never need, a button that does
     | nothing useful, and a drawer that can be opened by accident mid-entry.
     |
     | So the whole thing is removed rather than trimmed, and the screen
     | becomes what they actually use.
     */
    $plain = $user->isDataEntryOnly();
@endphp

<div class="shell {{ $plain ? 'shell--plain' : '' }}">

    @unless ($plain)
        @include('partials.sidebar')
    @endunless

    <header class="topbar">

        @if ($plain)
            {{-- No drawer to open, so the space carries the mark instead.
                 Tapping it goes home — the one navigation a worker needs. --}}
            <a class="topbar-home" href="{{ route('daily.index') }}" aria-label="Go to my work">
                @if (file_exists(public_path('images/logo.png')))
                    <img src="{{ asset('images/logo.png') }}" alt="" class="topbar-mark">
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
                         class="topbar-mark-icon">
                        <path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V20h14V9.5"/>
                    </svg>
                @endif
            </a>
        @else
            <button type="button" class="icon-btn icon-btn--menu" id="menuButton"
                    aria-label="Open menu" aria-controls="drawer" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" aria-hidden="true">
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>
            </button>
        @endif

        <div>
            <h1>@yield('title', 'Dashboard')</h1>
            <span class="topbar-sub">
                {{ $plain ? $user->displayName() : $user->roleSummary() }}
            </span>
        </div>

        <form method="POST" action="{{ route('logout') }}" class="topbar-end">
            @csrf
            <button type="submit" class="icon-btn" aria-label="Sign out">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 17l5-5-5-5M20 12H9M12 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h6"/>
                </svg>
            </button>
        </form>
    </header>

    <main class="content">
        @if (session('status'))
            <div class="notice notice--ok">{{ session('status') }}</div>
        @endif

        @yield('content')
    </main>
</div>

@unless ($plain)
    <div class="drawer-scrim" id="drawerScrim" hidden></div>

    <script>
        /* Drawer. Plain JS on purpose — no build step, nothing to download. */
        (function () {
            var button = document.getElementById('menuButton');
            var drawer = document.getElementById('drawer');
            var scrim  = document.getElementById('drawerScrim');

            if (!button || !drawer || !scrim) return;

            function setOpen(open) {
                drawer.dataset.open = open ? 'true' : 'false';
                scrim.dataset.open  = open ? 'true' : 'false';
                scrim.hidden = !open;
                button.setAttribute('aria-expanded', open ? 'true' : 'false');
            }

            button.addEventListener('click', function () {
                setOpen(drawer.dataset.open !== 'true');
            });

            scrim.addEventListener('click', function () { setOpen(false); });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') setOpen(false);
            });
        })();
    </script>
@endunless

</body>
</html>
