<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#8B1E2D">
    <title>@yield('title', 'Brooks Agro Farm')</title>
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
    // Keep the permission-filtered sidebar available to every account.
    $plain = false;
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

        @if($plain)
            @include('partials.worker-language')
        @endif

        @unless($plain)
            <form class="topbar-search" role="search" method="GET" action="{{ route('search.index') }}"><button class="topbar-search__submit" type="submit" aria-label="Run search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button><input type="search" name="q" value="{{ request()->routeIs('search.index') ? request('q') : '' }}" placeholder="Search batches, animals, tasks…" aria-label="Search" minlength="2" required></form>
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

    @unless($plain)
        <nav class="context-nav" id="contextNav" aria-label="Current workspace navigation" hidden>
            <button type="button" class="context-nav__back" onclick="if(history.length>1){history.back()}else{location.href='{{ route('dashboard') }}'}">&larr; Back</button>
            <strong id="contextNavTitle">@yield('title', 'Current page')</strong>
            <div id="contextNavLinks"></div>
        </nav>
    @endunless

    <main class="content">
        @if (session('status'))
            <div class="notice notice--ok">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="notice notice--alarm" role="alert"><strong>Please check this page.</strong> {{ $errors->first() }}</div>
        @endif

        @unless($plain)@if($unreadNotifications->isNotEmpty())@php($latestNotification=$unreadNotifications->first())<div class="login-notification"><div><b>{{ $unreadNotifications->count() }} unread {{ Str::plural('notification',$unreadNotifications->count()) }}</b><span>{{ $latestNotification->subject }} &middot; {{ $latestNotification->created_at->diffForHumans() }}</span></div><form method="POST" action="{{ route('communications.read',$latestNotification) }}">@csrf<button class="btn btn--small">Open notification</button></form></div>@endif @endunless
        @if($directiveReminders->isNotEmpty())
            @php($overdueDirectiveCount = $directiveReminders->filter(fn($directive) => $directive->due_on?->isPast())->count())
            <aside class="directive-reminder directive-reminder--recurring {{ $overdueDirectiveCount ? 'directive-reminder--overdue' : '' }}" role="alert" aria-live="polite">
                <div class="directive-reminder__icon"><svg aria-hidden="true"><use href="#i-bell"></use></svg></div>
                <div class="directive-reminder__body">
                    <strong>{{ $directiveReminders->count() }} unfinished {{ Str::plural('directive', $directiveReminders->count()) }}</strong>
                    @foreach($directiveReminders->take(1) as $directive)
                        <span><b>{{ $directive->title }}</b> · {{ Str::headline($directive->status) }}@if($directive->scheduled_for) · Scheduled {{ $directive->scheduled_for->format('d M Y, H:i') }}@elseif($directive->due_on) · Due {{ $directive->due_on->format('d M Y') }}@endif @if($directive->due_on?->isPast())<em>OVERDUE</em>@endif</span>
                    @endforeach
                    @if($directiveReminders->count() > 1)<small>And {{ $directiveReminders->count() - 1 }} more unfinished.</small>@endif
                </div>
                <a class="btn btn--small" href="{{ route('directives.index') }}">Open directives</a>
            </aside>
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

    <script>
        /* Two-level workspace navigation: the sidebar selects a function;
           its permitted screens appear in the contextual bar. */
        (function () {
            var drawer = document.getElementById('drawer');
            var bar = document.getElementById('contextNav');
            var title = document.getElementById('contextNavTitle');
            var links = document.getElementById('contextNavLinks');
            if (!drawer || !bar || !title || !links) return;

            var sections = Array.from(drawer.querySelectorAll('details.hr-nav-section, details.nav-group'));
            var supplementary = Array.from(drawer.querySelectorAll(':scope > a.nav-link--supplementary'));
            var clean = function (value) {
                return (value || '').replace(/^[^A-Za-z0-9]+/, '').trim();
            };
            var iconFor = function (label) {
                var value = label.toLowerCase();
                if (/dashboard/.test(value)) return 'home';
                if (/receive/.test(value)) return 'box';
                if (/issue/.test(value)) return 'clipboard';
                if (/operational report/.test(value)) return 'factory';
                if (/executive view|board view/.test(value)) return 'chart';
                if (/vaccin/.test(value)) return 'syringe';
                if (/report/.test(value)) return 'file';
                if (/analytic|view/.test(value)) return 'chart';
                if (/decision|approval|follow/.test(value)) return 'check-circle';
                if (/people|employee|customer|staff|welfare|human/.test(value)) return 'users';
                if (/supplier|vendor|payable/.test(value)) return 'truck';
                if (/cash|bank/.test(value)) return 'cash';
                if (/account|payroll|finance|budget|resource/.test(value)) return 'coins';
                if (/sales|receipt/.test(value)) return 'receipt';
                if (/stock|inventory|store/.test(value)) return 'box';
                if (/monitor/.test(value)) return 'vet';
                if (/farm|production|unit/.test(value)) return 'grain';
                if (/communication|message|update/.test(value)) return 'message';
                if (/admin|govern|setting|security/.test(value)) return 'gear';
                if (/fleet|transport/.test(value)) return 'truck';
                if (/daily|operation|work/.test(value)) return 'clipboard';
                return 'file';
            };
            var addIcon = function (element, label) {
                if (element.querySelector(':scope > svg')) return;
                var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                var use = document.createElementNS('http://www.w3.org/2000/svg', 'use');
                svg.setAttribute('class', 'rail-icon');
                svg.setAttribute('aria-hidden', 'true');
                use.setAttribute('href', '#i-' + iconFor(label));
                svg.appendChild(use);
                element.prepend(svg);
            };
            var selectSection = function (section) {
                sections.forEach(function (item) { item.removeAttribute('data-context-active'); item.open = false; });
                section.setAttribute('data-context-active', 'true');
                var label = clean(section.querySelector(':scope > summary')?.textContent);
                title.textContent = label;
                links.replaceChildren();
                section.querySelectorAll(':scope > a.nav-link, :scope > details > a.nav-link').forEach(function (source) {
                    var link = source.cloneNode(true);
                    link.querySelectorAll('svg').forEach(function (icon) { icon.remove(); });
                    link.textContent = clean(link.textContent);
                    links.appendChild(link);
                });
                supplementary.forEach(function (source) {
                    var sourceLabel = clean(source.textContent);
                    var belongsHere = (/vaccin|supervisor/i.test(sourceLabel) && /monitor|operation|work|production|people|daily/i.test(label))
                        || (/management report/i.test(sourceLabel) && /report/i.test(label));
                    if (!belongsHere) return;
                    var link = source.cloneNode(true);
                    link.classList.remove('nav-link--supplementary');
                    link.querySelectorAll('svg').forEach(function (icon) { icon.remove(); });
                    link.textContent = sourceLabel;
                    links.appendChild(link);
                });
                bar.hidden = links.children.length === 0;
            };

            sections.forEach(function (section) {
                var summary = section.querySelector(':scope > summary');
                var label = clean(summary?.textContent);
                if (!summary) return;
                summary.title = label;
                summary.setAttribute('aria-label', label);
                addIcon(summary, label);
                summary.addEventListener('click', function (event) {
                    event.preventDefault();
                    selectSection(section);
                    /* A primary navigation choice must open a workspace, not
                       merely repaint the menu while leaving the dashboard in
                       place. The first permitted link is the section landing
                       page; the contextual bar then exposes its other screens. */
                    var destination = section.querySelector(':scope > a.nav-link, :scope > details > a.nav-link');
                    if (destination && destination.href && destination.href !== window.location.href) {
                        window.location.assign(destination.href);
                    }
                });
            });

            drawer.querySelectorAll(':scope > a.nav-link, .hr-nav > a.nav-link').forEach(function (link) {
                var label = clean(link.textContent);
                link.title = label;
                addIcon(link, label);
            });

            var activeSupplementary = supplementary.find(function (link) { return link.matches('[aria-current="page"]'); });
            var supplementarySection = activeSupplementary ? sections.find(function (section) {
                var sectionLabel = clean(section.querySelector(':scope > summary')?.textContent);
                var sourceLabel = clean(activeSupplementary.textContent);
                return (/management report/i.test(sourceLabel) && /report/i.test(sectionLabel))
                    || (/vaccin|supervisor/i.test(sourceLabel) && /monitor|operation|work|production|people|daily/i.test(sectionLabel));
            }) : null;
            var topLevelCurrent = drawer.querySelector(':scope > a:not(.nav-link--supplementary)[aria-current="page"], .hr-nav > a[aria-current="page"]');
            var current = sections.find(function (section) { return section.querySelector('[aria-current="page"]'); })
                || supplementarySection
                || (!topLevelCurrent ? sections.find(function (section) { return section.open; }) : null)
                || (!topLevelCurrent ? sections[0] : null);
            if (current) selectSection(current);
        })();
    </script>
@endunless

<script src="{{ asset('js/cost-calculator.js') }}?v={{ filemtime(public_path('js/cost-calculator.js')) }}"></script>
</body>
</html>
