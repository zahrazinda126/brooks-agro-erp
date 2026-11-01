@extends('layouts.app')

@section('title', 'Feed Types')

@section('content')

<div class="page-head page-head--split">
    <div>
        <h2>Feed types</h2>
        <p>What the farm feeds. Workers choose from this list.</p>
    </div>

    @if ($user->canDo('feed-types', 'create'))
        <a class="btn btn--inline" href="{{ route('feed-types.create') }}">Add a feed</a>
    @endif
</div>

@foreach ([
    'complete'   => 'Main feeds — fed on their own',
    'energy'     => 'Energy — mixed in',
    'protein'    => 'Protein — mixed in',
    'mineral'    => 'Minerals and shells',
    'supplement' => 'Supplements',
] as $key => $heading)

    @if ($groups->has($key))
        <h3 class="section-heading">{{ $heading }}</h3>

        <div class="card">
            @foreach ($groups[$key] as $feed)
                <div class="ft-row {{ $feed->is_active ? '' : 'ft-row--off' }}">
                    <div>
                        <strong>{{ $feed->name }}</strong>
                        @if (! $feed->is_active)
                            <span class="pill pill--muted">Hidden</span>
                        @endif
                        @if ($feed->note)
                            <em>{{ $feed->note }}</em>
                        @endif
                    </div>

                    <div class="ft-facts">
                        <span>{{ $feed->default_kg_per_bag }} kg bag</span>
                        @if ($feed->from_week !== null)
                            <span>week {{ $feed->from_week }}–{{ $feed->to_week ?? 'end' }}</span>
                        @endif
                        @if ($feed->cost_per_kg)
                            <span>{{ number_format((float) $feed->cost_per_kg) }}/kg</span>
                        @endif
                    </div>

                    @if ($user->canDo('feed-types', 'edit'))
                        <a class="link-button" href="{{ route('feed-types.edit', $feed) }}">Edit</a>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endforeach

<div class="card u-mt-18">
    <p class="card-label">Why this list matters</p>
    <p class="muted u-m0">
        Workers can only record what is here. If the store buys something new
        and it is not on this list, they will record the nearest wrong option
        instead — and the feed conversion figures will be wrong from that day
        on. Add it here first.
    </p>
</div>

@endsection
