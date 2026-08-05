{{--
    Built from the database, not written by hand. A person sees a link only
    if their role or department grants it, so the menu is itself a statement
    of their permissions.

    Two levels now. A group heading, then either a link or a heading that
    opens into more links:

        FINANCE
          Money In        ▸  Invoices, Receipts, Customers…
          The Books       ▸  Journal Entries, Cash Book…
          Payroll

    The second level exists because the Finance Manager's remit is about
    twenty-five screens. Flat, that is a wall of text where Cash Book and
    Customer Ledger look equally important and neither is findable.
--}}
@php
    $groups     = $user->sidebarModules();
    $currentKey = request()->route('key')
        ?? (request()->routeIs('dashboard') ? 'dashboard' : null)
        ?? (request()->routeIs('employees.*') ? 'hr' : null);
@endphp

<nav class="drawer" id="drawer" data-open="false" aria-label="Main menu">

    <div class="brand">
        @if (file_exists(public_path('images/logo.png')))
            <img src="{{ asset('images/logo.png') }}" alt="" class="brand-mark">
        @endif
        <span class="brand-name">Brooks Agro</span>
    </div>

    <div class="drawer-head">
        <strong>{{ $user->displayName() }}</strong>
        <span>{{ $user->departmentSummary() }}</span>
    </div>

    <div class="nav-filter">
        <input type="search"
               id="navFilter"
               placeholder="Find a screen"
               aria-label="Find a screen"
               autocomplete="off">
    </div>

    <div id="navGroups">
        @forelse ($groups as $groupName => $modules)
            @php
                $holdsCurrent = $modules->contains(function ($m) use ($currentKey) {
                    return $m->key === $currentKey
                        || $m->children->contains('key', $currentKey);
                });
            @endphp

            <details class="nav-group"
                     data-group="{{ Str::slug($groupName) }}"
                     @if ($holdsCurrent) open @endif>

                <summary>
                    <span>{{ $groupName }}</span>
                    <em>{{ $modules->count() }}</em>
                </summary>

                @foreach ($modules as $module)

                    @if ($module->children->isNotEmpty())
                        @php
                            $childCurrent = $module->children->contains('key', $currentKey);
                        @endphp

                        {{-- A heading that opens into more links. --}}
                        <details class="nav-sub"
                                 data-group="sub-{{ $module->key }}"
                                 @if ($childCurrent) open @endif>

                            <summary>
                                <svg class="nav-icon" aria-hidden="true">
                                    <use href="#i-{{ $module->iconName() }}"></use>
                                </svg>
                                <span>{{ $module->name }}</span>
                                <em>{{ $module->children->count() }}</em>
                            </summary>

                            {{-- A parent that is also a screen keeps its own link. --}}
                            @if (! $module->isContainer())
                                <a class="nav-link nav-link--child"
                                   href="{{ $module->url() }}"
                                   data-name="{{ Str::lower($module->name) }}"
                                   @if ($module->key === $currentKey) aria-current="page" @endif>
                                    <span>{{ $module->name }} overview</span>
                                </a>
                            @endif

                            @foreach ($module->children as $child)
                                <a class="nav-link nav-link--child"
                                   href="{{ $child->url() }}"
                                   data-name="{{ Str::lower($child->name) }}"
                                   @if ($child->key === $currentKey) aria-current="page" @endif>
                                    <span>{{ $child->name }}</span>
                                </a>
                            @endforeach
                        </details>

                    @else
                        <a class="nav-link"
                           href="{{ $module->url() }}"
                           data-name="{{ Str::lower($module->name) }}"
                           @if ($module->key === $currentKey) aria-current="page" @endif>
                            <svg class="nav-icon" aria-hidden="true">
                                <use href="#i-{{ $module->iconName() }}"></use>
                            </svg>
                            <span>{{ $module->name }}</span>
                        </a>
                    @endif

                @endforeach
            </details>
        @empty
            <p class="nav-empty">
                No screens have been assigned to your account yet. Ask HR to check your role.
            </p>
        @endforelse
    </div>

    <p class="nav-none" id="navNone" hidden>Nothing matches that.</p>

    <form method="POST" action="{{ route('logout') }}" class="nav-signout">
        @csrf
        <button type="submit" class="nav-link nav-link--button">
            <svg class="nav-icon" aria-hidden="true"><use href="#i-lock"></use></svg>
            <span>Sign out</span>
        </button>
    </form>
</nav>

<script>
(function () {
    var filter = document.getElementById('navFilter');
    var groups = document.getElementById('navGroups');
    var none   = document.getElementById('navNone');
    if (!filter || !groups) return;

    var STORE = 'brooks.nav.open';

    /* Remember which groups and sub-groups the user leaves open. */
    function remember() {
        var open = [];
        groups.querySelectorAll('details[open]').forEach(function (d) {
            open.push(d.dataset.group);
        });
        try { localStorage.setItem(STORE, JSON.stringify(open)); } catch (e) {}
    }

    try {
        var saved = JSON.parse(localStorage.getItem(STORE) || '[]');
        groups.querySelectorAll('details').forEach(function (d) {
            if (saved.indexOf(d.dataset.group) !== -1) d.open = true;
        });
    } catch (e) {}

    groups.querySelectorAll('details').forEach(function (d) {
        d.addEventListener('toggle', remember);
    });

    /* Typing opens everything and hides non-matching links. Clearing the
       box puts the previous state back. */
    var before = null;

    filter.addEventListener('input', function () {
        var term = filter.value.trim().toLowerCase();

        if (term && before === null) {
            before = [];
            groups.querySelectorAll('details').forEach(function (d) {
                before.push(d.open);
                d.open = true;
            });
        }

        var hits = 0;

        // Links first, then hide any container left with nothing showing.
        groups.querySelectorAll('.nav-link').forEach(function (a) {
            if (!a.dataset.name) return;
            var match = !term || a.dataset.name.indexOf(term) !== -1;
            a.hidden = !match;
            if (match) hits++;
        });

        groups.querySelectorAll('.nav-sub').forEach(function (sub) {
            var shown = sub.querySelectorAll('.nav-link:not([hidden])').length;
            sub.hidden = term && shown === 0;
        });

        groups.querySelectorAll('.nav-group').forEach(function (g) {
            var shownLinks = g.querySelectorAll('.nav-link:not([hidden])').length;
            g.hidden = term && shownLinks === 0;
        });

        none.hidden = hits > 0;

        if (!term && before !== null) {
            groups.querySelectorAll('details').forEach(function (d, i) {
                d.open = before[i];
            });
            before = null;
        }
    });
})();
</script>
