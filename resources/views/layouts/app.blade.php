<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#fbf1ec">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth<meta name="page-export-url" content="{{ route('page.export', [], false) }}">@endauth
    <title>@yield('title', 'Brooks Agro Farm')</title>
    <link rel="preload" href="{{ asset('fonts/inter-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/space-grotesk-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/farmhub.css') }}?v={{ filemtime(public_path('css/farmhub.css')) }}">
    {{-- Report footer on every printed page, from config/farm.php --}}
    <style>
        @media print {
            @page {
                @top-center { content: ""; border-top: 2.5mm solid #9b292d; vertical-align: top; }
                @bottom-center {
                    content: "{{ addslashes(config('farm.name')) }}  |  {{ addslashes(config('farm.address')) }}  |  Tel: {{ addslashes(config('farm.phone')) }}" "\A" "Confidential. {{ addslashes(config('farm.name')) }} management document  ·  Page " counter(page) " of " counter(pages);
                    white-space: pre;
                    font: 7.5pt/1.5 "Inter", Arial, sans-serif;
                    color: #555;
                    border-top: .5pt solid #cfcfcf;
                }
            }
        }
    </style>
    @stack('styles')
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
    // Farm workers (casual workers, poultry attendants) get the plain screen; everyone else keeps the sidebar.
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

        <a class="topbar-brand" href="{{ $plain ? route('daily.index') : route('dashboard') }}" aria-label="Brooks Agro Farm home">
            @if (! $plain && file_exists(public_path('images/logo.png')))<img src="{{ asset('images/logo.png') }}" alt="">@endif
            <span><strong>BROOKS AGRO FARM</strong><small>Uganda · UGX</small></span>
        </a>

        <div class="topbar-title">
            <h1>@yield('title', 'Dashboard')</h1>
            <span class="topbar-sub">
                {{ $plain ? $user->displayName() : $user->roleSummary() }}
            </span>
        </div>

        @if($plain)
            @php
                $unreadMessages = \App\Models\Communication::where('recipient_id', $user->id)->whereNull('read_at')->count();
            @endphp
            <a class="icon-btn worker-messages" href="{{ route('communications.index') }}" aria-label="Messages{{ $unreadMessages ? ' ('.$unreadMessages.' new)' : '' }}" title="Messages">
                <svg aria-hidden="true"><use href="#i-bell"></use></svg>
                @if($unreadMessages)<span class="worker-messages__count">{{ $unreadMessages > 9 ? '9+' : $unreadMessages }}</span>@endif
            </a>
            @if($user->role?->code === 'poultry-attendant' || $user->roles()->where('code', 'poultry-attendant')->exists())
                <a class="attendant-topbar-user" href="{{ route('account.edit') }}" aria-label="Account: {{ $user->displayName() }}">
                    <span>{{ strtoupper(substr($user->firstName(), 0, 1)) }}</span><strong>{{ $user->displayName() }}</strong><i aria-hidden="true"></i>
                </a>
            @endif
        @endif

        @unless($plain)
            <form class="topbar-search" role="search" method="GET" action="{{ route('search.index') }}"><button class="topbar-search__submit" type="submit" aria-label="Run search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></button><input type="search" name="q" value="{{ request()->routeIs('search.index') ? request('q') : '' }}" placeholder="Search batches, animals, tasks…" aria-label="Search" minlength="2" required></form>
            @php($unreadNotifications=\App\Models\Communication::where('recipient_id',$user->id)->whereNull('read_at')->latest()->get())
            <a class="icon-btn topbar-bell" href="{{ route('communications.index') }}" aria-label="Notifications{{ $unreadNotifications->count() ? ' ('.$unreadNotifications->count().' unread)' : '' }}" title="Notifications"><svg aria-hidden="true"><use href="#i-bell"></use></svg>@if($unreadNotifications->count())<b>{{ $unreadNotifications->count() > 99 ? '99+' : $unreadNotifications->count() }}</b>@endif</a>
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
        <nav class="context-nav" id="contextNav" aria-label="Current workspace navigation">
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

        @unless($plain)@if($unreadNotifications->isNotEmpty())@php($latestNotification=$unreadNotifications->first())<div class="login-notification"><div><b>{{ $unreadNotifications->count() }} unread {{ Str::plural('notification',$unreadNotifications->count()) }}</b><span>{{ $latestNotification->subject }} &middot; {{ $latestNotification->created_at->diffForHumans() }}</span></div><a class="btn btn--small" href="{{ route('communications.index') }}">{{ $unreadNotifications->count() === 1 ? 'Open notification' : 'Open notifications' }}</a></div>@endif @endunless
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
        <?php
            /* Screens without their own heading get one in the Farm Hub style,
               so every page opens with its title in the same place.
               (Plain PHP tags: this file also uses the one-line @php(...) form,
               which Blade confuses with a multi-line @php block.) */
            $pageBody = $__env->yieldContent('content');
            // A report header (x-report-header) only shows on paper, so it does not count as an on-screen title,
            // and neither does a page-head straight after it (app.css hides that one).
            $screenBody = preg_replace('/<header class="standard-report-head">.*?<\/header>\s*<div class="page-head[^"]*">/s', '', substr($pageBody, 0, 12000));
            $hasOwnHead = preg_match('/page-head|report-letterhead|entry-head|-hero\b|attendant-hero|executive-workspace__head|"fd-head"|"dash__head"/', substr($screenBody, 0, 6000));
        ?>
        {{-- Paper only: every printed screen carries the farm letterhead, a top strip and the logo watermark. --}}
        @include('partials.print-letterhead', ['title' => trim($__env->yieldContent('title', 'Report')), 'scope' => null, 'period' => null, 'class' => 'print-letterhead--page'])
        <div class="print-strip" aria-hidden="true"></div>
        @if (file_exists(public_path('images/logo-round.png')))<img class="print-watermark" src="{{ asset('images/logo-round.png') }}" alt="" aria-hidden="true">@endif
        @if(! $plain && array_key_exists((string) request('farmunit'), \App\Support\MonitorTasks::UNITS))
            @include('partials.farm-unit-tabs', ['farmUnit' => request('farmunit')])
        @endif
        @unless($hasOwnHead)
            <div class="page-head page-head--auto"><h2>@yield('title', 'Dashboard')</h2></div>
        @endunless
        {!! $pageBody !!}
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

            /* Report centre (Farm Hub "Reports"): one place listing every report, straight after Dashboard. */
            var dashboardLink = drawer.querySelector('a.nav-link[href$="/dashboard"]');
            if (dashboardLink && @json(count(\App\Http\Controllers\ReportCentreController::reportsFor($user)) > 0)) {
                var centre = document.createElement('a');
                centre.className = 'nav-link';
                centre.href = @json(route('reports.centre'));
                centre.innerHTML = '<svg class="nav-icon" aria-hidden="true"><use href="#i-file"></use></svg><span>Report centre</span>';
                if (@json(request()->routeIs('reports.centre'))) {
                    centre.setAttribute('aria-current', 'page');
                    dashboardLink.removeAttribute('aria-current');
                }
                dashboardLink.after(centre);
            }
            /* Stock requests (Farm Hub warehouse flow): ask a store for items, approve, prepare, receive. */
            if (dashboardLink) {
                var requests = document.createElement('a');
                requests.className = 'nav-link';
                requests.href = @json(route('inventory.requests.index'));
                requests.innerHTML = '<svg class="nav-icon" aria-hidden="true"><use href="#i-box"></use></svg><span>Stock requests</span>';
                if (@json(request()->routeIs('inventory.requests.*'))) {
                    requests.setAttribute('aria-current', 'page');
                    dashboardLink.removeAttribute('aria-current');
                }
                var anchor = drawer.querySelector('a.nav-link[href$="/report-centre"]') || dashboardLink;
                anchor.after(requests);
                /* Farm registers: house preparation, incidents & near misses, faults & maintenance. */
                var registers = document.createElement('a');
                registers.className = 'nav-link';
                registers.href = @json(route('farm-registers.index'));
                registers.innerHTML = '<svg class="nav-icon" aria-hidden="true"><use href="#i-clipboard"></use></svg><span>Farm registers</span>';
                if (@json(request()->routeIs('farm-registers.*'))) {
                    registers.setAttribute('aria-current', 'page');
                    dashboardLink.removeAttribute('aria-current');
                }
                requests.after(registers);
            }

            var sections = Array.from(drawer.querySelectorAll('details.hr-nav-section, details.nav-group'));
            var supplementary = Array.from(drawer.querySelectorAll(':scope > a.nav-link--supplementary'));
            var clean = function (value) {
                return (value || '').replace(/^[^A-Za-z0-9]+/, '').trim();
            };
            /* A heading's name without its screen count (module menus show "Poultry 11"). */
            var labelOf = function (summary) {
                if (!summary) return '';
                var name = summary.querySelector(':scope > span');
                return clean((name || summary).textContent);
            };
            var exactIcons = {'finance posting': 'clipboard', 'receivables': 'receipt', 'customers': 'users', 'payables': 'truck', 'expenses': 'coins', 'budget & funding': 'calendar',
                'cash & banking': 'cash', 'sales': 'chart', 'purchases': 'cart', 'inventory': 'box', 'payroll': 'users', 'fixed assets': 'wrench', 'accounting': 'ledger', 'settings': 'gear'};
            var iconFor = function (label) {
                var value = label.toLowerCase();
                if (exactIcons[value.trim()]) return exactIcons[value.trim()];
                if (/dashboard/.test(value)) return 'home';
                if (/goat|rabbit|dog|livestock/.test(value)) return 'paw';
                if (/garden|crop/.test(value)) return 'grain';
                if (/^poultry$/.test(value.trim())) return 'egg';
                if (/^settings$/.test(value.trim())) return 'gear';
                if (/receive/.test(value)) return 'box';
                if (/issue/.test(value)) return 'clipboard';
                if (/operational report/.test(value)) return 'factory';
                if (/executive view|board view/.test(value)) return 'chart';
                if (/vaccin/.test(value)) return 'syringe';
                if (/welfare\s*&\s*development/.test(value)) return 'cap';
                if (/people\s*&\s*payroll/.test(value)) return 'users';
                if (/salary|wage/.test(value)) return 'cash';
                if (/report/.test(value)) return 'file';
                if (/analytic|view/.test(value)) return 'chart';
                if (/decision|approval|follow/.test(value)) return 'check-circle';
                if (/people|employee|customer|staff|welfare|human/.test(value)) return 'users';
                if (/supplier|vendor|payable/.test(value)) return 'truck';
                if (/cash|bank/.test(value)) return 'cash';
                if (/accounting|ledger|books/.test(value)) return 'ledger';
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
            /* Every sidebar section has an overview page: /overview/{slug}?name=Label */
            var slugOf = function (label) {
                return label.toLowerCase().replace(/&/g, ' and ').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
            };
            var overviewTemplate = @json(route('section.overview', ['section' => '__SECTION__'], false));
            /* The section's first screen travels along: a section with nothing to summarise opens it directly. */
            var overviewUrl = function (label, section) {
                var first = section ? section.querySelector(':scope > a.nav-link[href], :scope > details > a.nav-link[href]') : null;
                var firstPath = first ? new URL(first.href, window.location.href) : null;
                return overviewTemplate.replace('__SECTION__', slugOf(label)) + '?name=' + encodeURIComponent(label)
                    + (firstPath ? '&first=' + encodeURIComponent(firstPath.pathname + firstPath.search) : '');
            };
            var overviewPage = document.querySelector('[data-section-overview]');
            var selectSection = function (section) {
                sections.forEach(function (item) { item.removeAttribute('data-context-active'); item.open = false; });
                section.setAttribute('data-context-active', 'true');
                var label = labelOf(section.querySelector(':scope > summary'));
                title.textContent = label;
                links.replaceChildren();
                var sectionTab = document.createElement('a');
                sectionTab.className = 'nav-link context-nav__section';
                sectionTab.href = overviewUrl(label, section);
                sectionTab.textContent = label;
                var onOverview = overviewPage && overviewPage.dataset.sectionOverview === slugOf(label);
                if (onOverview) sectionTab.setAttribute('aria-current', 'page');
                links.appendChild(sectionTab);
                section.querySelectorAll(':scope > a.nav-link, :scope > details > a.nav-link').forEach(function (source) {
                    var link = source.cloneNode(true);
                    link.querySelectorAll('svg').forEach(function (icon) { icon.remove(); });
                    link.textContent = clean(link.textContent);
                    links.appendChild(link);
                });
                /* A section whose only tab has the same name shows one tab, not "Inventory · Inventory". */
                /* …and a section whose first tab is its overview ("Customer Overview") shows that tab in the section's place. */
                var sameName = Array.from(links.querySelectorAll('a.nav-link:not(.context-nav__section)')).find(function (tab) { var t = tab.textContent.trim().toLowerCase(); return t === label.trim().toLowerCase() || /\boverview$/.test(t); });
                if (sameName) {
                    sectionTab.remove();
                    sameName.classList.add('context-nav__section');
                }
                supplementary.forEach(function (source) {
                    var sourceLabel = clean(source.textContent);
                    var belongsHere = (/vaccin|supervisor/i.test(sourceLabel) && /monitor|operation|work|production|people|daily/i.test(label) && !/sale/i.test(label))
                        || (/management report/i.test(sourceLabel) && /report/i.test(label));
                    if (!belongsHere) return;
                    /* Vaccination lives inside the Health & Treatment tab where that tab exists. */
                    if (/vaccin/i.test(sourceLabel) && Array.from(links.querySelectorAll('a')).some(function (tab) { return /health\s*&\s*treatment/i.test(tab.textContent); })) return;
                    /* Skip the shortcut when this section already has a tab for the same page. */
                    var target = source.getAttribute('href').split('?')[0].split('#')[0];
                    if (Array.from(links.querySelectorAll('a[href]')).some(function (tab) { return tab.getAttribute('href').split('?')[0].split('#')[0] === target; })) return;
                    var link = source.cloneNode(true);
                    link.classList.remove('nav-link--supplementary');
                    link.querySelectorAll('svg').forEach(function (icon) { icon.remove(); });
                    link.textContent = sourceLabel;
                    links.appendChild(link);
                });
                bar.hidden = false; // always visible: it names the current page even without tabs
                if (onOverview) {
                    var grid = document.getElementById('sectionScreens');
                    if (grid) {
                        grid.replaceChildren();
                        links.querySelectorAll('a.nav-link:not(.context-nav__section)').forEach(function (tab) {
                            var card = document.createElement('a');
                            card.href = tab.href;
                            card.textContent = tab.textContent;
                            grid.appendChild(card);
                        });
                    }
                    /* On the overview, no screen tab is "current": only the section tab. */
                    links.querySelectorAll('a.nav-link:not(.context-nav__section)[aria-current]').forEach(function (tab) { tab.removeAttribute('aria-current'); });
                }
            };

            sections.forEach(function (section) {
                var summary = section.querySelector(':scope > summary');
                var label = labelOf(summary);
                if (!summary) return;
                summary.title = label;
                summary.setAttribute('aria-label', label);
                addIcon(summary, label);
                summary.addEventListener('click', function (event) {
                    event.preventDefault();
                    selectSection(section);
                    /* A section opens its own overview page (summary + its screens);
                       the bar then offers the section's screens as tabs. */
                    var destination = overviewUrl(label, section);
                    if (!(overviewPage && overviewPage.dataset.sectionOverview === slugOf(label))) {
                        window.location.assign(destination);
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
                var sectionLabel = labelOf(section.querySelector(':scope > summary'));
                var sourceLabel = clean(activeSupplementary.textContent);
                return (/management report/i.test(sourceLabel) && /report/i.test(sectionLabel))
                    || (/vaccin|supervisor/i.test(sourceLabel) && /monitor|operation|work|production|people|daily/i.test(sectionLabel));
            }) : null;
            var topLevelCurrent = drawer.querySelector(':scope > a:not(.nav-link--supplementary)[aria-current="page"], .hr-nav > a[aria-current="page"]');
            var overviewSection = overviewPage ? sections.find(function (section) {
                return slugOf(labelOf(section.querySelector(':scope > summary'))) === overviewPage.dataset.sectionOverview;
            }) : null;
            var current = overviewSection
                || sections.find(function (section) { return section.querySelector('[aria-current="page"]'); })
                || supplementarySection
                || (!topLevelCurrent ? sections.find(function (section) { return section.open; }) : null);
            if (current) selectSection(current);

            /* Farm Hub layout: the section tabs sit under the page title. */
            var content = document.querySelector('main.content');
            if (content) {
                var head = content.querySelector(':scope > .page-head, :scope > div > .page-head:first-child');
                if (head) head.after(bar); else content.prepend(bar);
                /* The Back button (kept on every page, as agreed) sits above the title. */
                var back = bar.querySelector('.context-nav__back');
                if (back) { back.classList.add('page-back'); content.prepend(back); }
                /* Farm Units › a unit: the unit's own tabs sit under the Farm Units tabs, and filters keep the unit. */
                var unitBar = content.querySelector('[data-farm-unit-bar]');
                if (unitBar) {
                    bar.after(unitBar);
                    var unit = new URLSearchParams(location.search).get('farmunit');
                    content.querySelectorAll('form').forEach(function (form) {
                        if ((form.getAttribute('method') || 'get').toLowerCase() !== 'get' || form.querySelector('[name="farmunit"]')) return;
                        var keep = document.createElement('input');
                        keep.type = 'hidden'; keep.name = 'farmunit'; keep.value = unit;
                        form.appendChild(keep);
                    });
                }
            }

            /* Bottom bar on phones: the first four places in this person's menu, then More. */
            var places = [];
            drawer.querySelectorAll(':scope > a.nav-link:not(.nav-link--supplementary), .hr-nav > a.nav-link, details.hr-nav-section, details.nav-group').forEach(function (item) {
                if (places.length >= 4) return;
                if (item.tagName === 'DETAILS') {
                    var label = labelOf(item.querySelector(':scope > summary'));
                    if (label) places.push({ label: label, href: overviewUrl(label, item), icon: iconFor(label), active: item === current });
                } else {
                    var text = clean(item.textContent);
                    var use = item.querySelector('use');
                    if (text) places.push({ label: text, href: item.href, icon: use ? use.getAttribute('href').replace('#i-', '') : iconFor(text), active: item.matches('[aria-current="page"]') });
                }
            });
            if (places.length) {
                var bottom = document.createElement('nav');
                bottom.className = 'bottom-nav';
                bottom.setAttribute('aria-label', 'Quick');
                var icon = function (name) {
                    return '<svg aria-hidden="true"><use href="#i-' + name + '"></use></svg>';
                };
                places.forEach(function (place) {
                    var a = document.createElement('a');
                    a.href = place.href;
                    a.innerHTML = icon(place.icon) + '<span></span>';
                    a.querySelector('span').textContent = place.label;
                    if (place.active) a.setAttribute('aria-current', 'page');
                    bottom.appendChild(a);
                });
                var more = document.createElement('button');
                more.type = 'button';
                more.innerHTML = icon('menu') + '<span>More</span>';
                more.addEventListener('click', function () { document.getElementById('menuButton').click(); });
                bottom.appendChild(more);
                bottom.style.setProperty('--bottom-nav-count', places.length + 1);
                document.body.appendChild(bottom);
                document.body.classList.add('has-bottom-nav');
            }
        })();
    </script>
@endunless

<script>
    /* Printing a report: open every folded section first so nothing the page holds is left off the paper,
       then fold back what was closed. */
    (function () {
        var reopened = [];
        window.addEventListener('beforeprint', function () {
            reopened = Array.from(document.querySelectorAll('main.content details:not([open])'));
            reopened.forEach(function (d) { d.open = true; });
        });
        window.addEventListener('afterprint', function () {
            reopened.forEach(function (d) { d.open = false; });
            reopened = [];
        });
    })();
</script>
<script src="{{ asset('js/cost-calculator.js') }}?v={{ filemtime(public_path('js/cost-calculator.js')) }}"></script>
<script src="{{ asset('js/app-ui.js') }}?v={{ filemtime(public_path('js/app-ui.js')) }}"></script>
</body>
</html>
