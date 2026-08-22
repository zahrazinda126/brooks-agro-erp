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

        <div class="topbar-title">
            <h1>@yield('title', 'Dashboard')</h1>
            <span class="topbar-sub">
                {{ $plain ? $user->displayName() : $user->roleSummary() }}
            </span>
        </div>

        @unless($plain)
            <div class="topbar-search" role="search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input type="search" placeholder="Search batches, animals, tasks…" aria-label="Search"></div>
            @php($unreadNotifications=\App\Models\Communication::where('recipient_id',$user->id)->whereNull('read_at')->latest()->get())
            <a class="topbar-user" href="{{ route('account.edit') }}" title="Edit my account"><span>{{ strtoupper(substr($user->firstName(),0,1).substr($user->displayName(),-1)) }}</span><div><strong>{{ $user->displayName() }}</strong><small>{{ $user->roleSummary() }} &middot; Edit account</small></div></a>
        @endunless
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
        @if ($errors->any())
            <div class="notice notice--alarm" role="alert"><strong>Please check this page.</strong> {{ $errors->first() }}</div>
        @endif

        @unless($plain)@if($unreadNotifications->isNotEmpty())@php($latestNotification=$unreadNotifications->first())<div class="login-notification"><div><b>{{ $unreadNotifications->count() }} unread {{ Str::plural('notification',$unreadNotifications->count()) }}</b><span>{{ $latestNotification->subject }} &middot; {{ $latestNotification->created_at->diffForHumans() }}</span></div><form method="POST" action="{{ route('communications.read',$latestNotification) }}">@csrf<button class="btn btn--small">Open notification</button></form></div>@endif @endunless
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

            if (!drawer) return;

            /* A full page navigation rebuilds the fixed drawer. Remember its
               position per signed-in user so lower menu items stay in view. */
            var scrollKey = 'brooks.drawer.scroll.{{ auth()->id() }}';
            try {
                var savedScroll = parseInt(sessionStorage.getItem(scrollKey) || '0', 10);
                if (Number.isFinite(savedScroll)) drawer.scrollTop = savedScroll;
            } catch (e) {}

            function rememberDrawerPosition() {
                try { sessionStorage.setItem(scrollKey, String(drawer.scrollTop)); } catch (e) {}
            }

            drawer.addEventListener('scroll', rememberDrawerPosition, { passive: true });
            drawer.querySelectorAll('a[href], form').forEach(function (item) {
                item.addEventListener(item.tagName === 'FORM' ? 'submit' : 'click', rememberDrawerPosition);
            });
            window.addEventListener('pagehide', rememberDrawerPosition);

            if (!button || !scrim) return;

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

<script src="{{ asset('js/cost-calculator.js') }}?v={{ filemtime(public_path('js/cost-calculator.js')) }}"></script>
</body>
</html>
