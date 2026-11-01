{{-- Cash/Bank Book filters. Expects $routeName, $accounts, $selected, $filters, $allLabel. --}}
<form method="GET" class="card book-filters">
    <div class="book-filters__periods" role="group" aria-label="Quick period">
        @foreach(['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $key => $label)
            <a class="btn btn--quiet btn--small {{ $filters['period'] === $key ? 'is-active' : '' }}" href="{{ route($routeName, array_filter(['period' => $key, 'account' => $selected])) }}" @if($filters['period'] === $key) aria-current="true" @endif>{{ $label }}</a>
        @endforeach
    </div>
    <div class="book-filters__grid">
        <div class="field"><label>Account</label><select name="account"><option value="">{{ $allLabel }}</option>@foreach($accounts as $account)<option value="{{ $account }}" @selected($selected === $account)>{{ $account }}</option>@endforeach</select></div>
        <div class="field"><label>From</label><input type="date" name="from" value="{{ $filters['from'] }}"></div>
        <div class="field"><label>To</label><input type="date" name="to" value="{{ $filters['to'] }}"></div>
        <div class="field"><label>Money flow</label><select name="direction"><option value="">All entries</option><option value="in" @selected($filters['direction'] === 'in')>Money in</option><option value="out" @selected($filters['direction'] === 'out')>Money out</option><option value="transfer" @selected($filters['direction'] === 'transfer')>Transfers</option></select></div>
        <div class="field book-filters__search"><label>Search</label><input name="search" value="{{ $filters['search'] }}" placeholder="Item, reference, customer or supplier"></div>
        <div class="book-filters__actions"><button class="btn">Apply</button><a class="btn btn--quiet" href="{{ route($routeName) }}">Clear</a></div>
    </div>
</form>
