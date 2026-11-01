@extends('layouts.app')

@section('title', 'Breeds')

@section('content')

<div class="page-head page-head--split">
    <div>
        <h2>Breeds</h2>
        <p>What each breed is expected to do. This is where a batch gets its timetable.</p>
    </div>

    @if ($user->canDo('breeds', 'create'))
        <a class="btn btn--inline" href="{{ route('breeds.create') }}">Add a breed</a>
    @endif
</div>

@if ($groups->isEmpty())
    <div class="card">
        <p class="card-label">No breeds</p>
        <p class="muted u-m0-b14">
            Add the breeds you keep. The figures are on the delivery note or breed
            sheet that comes with the birds.
        </p>
        @if ($user->canDo('breeds', 'create'))
            <a class="btn btn--inline" href="{{ route('breeds.create') }}">Add a breed</a>
        @endif
    </div>
@else
    @foreach (['layer' => 'Layers', 'dual' => 'Dual purpose', 'broiler' => 'Broilers'] as $purpose => $heading)
        @if ($groups->has($purpose))
            <h3 class="section-heading">{{ $heading }}</h3>

            <div class="breed-list">
                @foreach ($groups[$purpose] as $breed)
                    <div class="breed-card {{ $breed->is_active ? '' : 'breed-card--off' }}">

                        <div class="breed-card-head">
                            <div>
                                <strong>{{ $breed->name }}</strong>
                                @if (! $breed->is_active)
                                    <span class="pill pill--muted">Hidden</span>
                                @endif
                                <span class="breed-usage">
                                    {{ $breed->batches_count }} {{ Str::plural('batch', $breed->batches_count) }}
                                </span>
                            </div>

                            @if ($user->canDo('breeds', 'edit'))
                                <a class="btn btn--quiet btn--inline btn--small"
                                   href="{{ route('breeds.edit', $breed) }}">Edit</a>
                            @endif
                        </div>

                        @if ($breed->purpose === 'broiler')
                            <dl class="facts">
                                <dt>Ready at</dt>
                                <dd>Week {{ $breed->slaughter_week ?? '—' }}</dd>
                                <dt>Weight</dt>
                                <dd>{{ $breed->mature_weight_g ? number_format($breed->mature_weight_g) . ' g' : '—' }}</dd>
                            </dl>
                        @else
                            {{-- The laying span, drawn as a line. Easier to
                                 compare two breeds at a glance than four numbers. --}}
                            <div class="lay-span">
                                <span>Week {{ $breed->lay_start_week ?? '?' }}</span>
                                <span class="lay-bar" aria-hidden="true"></span>
                                <span>{{ $breed->lay_end_week ?? '?' }}</span>
                            </div>

                            <dl class="facts">
                                <dt>Starts</dt><dd>Week {{ $breed->lay_start_week ?? '—' }}</dd>
                                <dt>Peak</dt>
                                <dd>
                                    Week {{ $breed->peak_week ?? '—' }}
                                    @if ($breed->peak_lay_percent)
                                        <span class="muted">at {{ $breed->peak_lay_percent }}%</span>
                                    @endif
                                </dd>
                                <dt>Ends</dt><dd>Week {{ $breed->lay_end_week ?? '—' }}</dd>
                                <dt>Feed</dt>
                                <dd>{{ $breed->daily_feed_g ? $breed->daily_feed_g . ' g/day' : '—' }}</dd>
                            </dl>
                        @endif

                        @if ($breed->notes)
                            <p class="breed-notes">{{ $breed->notes }}</p>
                        @endif

                        @if ($user->canDo('breeds', 'edit'))
                            <form method="POST" action="{{ route('breeds.toggle', $breed) }}" style="margin-top:10px;">
                                @csrf
                                <button type="submit" class="link-button">
                                    {{ $breed->is_active ? 'Hide from the batch form' : 'Make available again' }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    @endforeach
@endif

<div class="card u-mt-18">
    <p class="card-label">Where these figures come from</p>
    <p class="muted u-m0">
        Hatcheries supply a breed sheet or delivery note with the point of lay,
        peak week and expected rate. Copy those in and every batch of that breed
        gets its timetable automatically — when eggs are due, what size to expect,
        and when the flock is falling behind.
    </p>
</div>

@endsection
