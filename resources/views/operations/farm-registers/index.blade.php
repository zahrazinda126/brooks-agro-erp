@extends('layouts.app')
@section('title', 'Farm registers')
@section('content')
@php
    $meta = $registers[$register];
    $statusLabel = ['open' => ['Open', 'pending'], 'resolved' => ['Closed', 'approved'], 'ready' => ['House ready', 'approved'], 'not_ready' => ['Not ready', 'rejected']];
@endphp
<div class="page-head page-head--split">
    <div>
        <p class="eyebrow">Operations</p>
        <h1>Farm registers</h1>
        <p>Entered by whoever did the work or saw it; closed by a manager with a note.</p>
    </div>
    <button type="button" class="btn btn--inline" data-open-register>New {{ strtolower($register === 'house_preparation' ? 'house check' : ($register === 'incident' ? 'incident' : 'fault')) }}</button>
</div>
@if(session('status'))<div class="saved"><strong>{{ session('status') }}</strong></div>@endif
@if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif

<nav class="view-switch fr-tabs" aria-label="Registers" data-keep-link>
    @foreach($registers as $key => $r)
        <a href="{{ route('farm-registers.index', ['register' => $key]) }}" @if($register === $key) aria-current="page" @endif>{{ $r['label'] }}@if(($openCounts[$key] ?? 0) > 0) <span class="inv-count">{{ $openCounts[$key] }}</span>@endif</a>
    @endforeach
</nav>
<p class="muted">{{ $meta['about'] }}</p>

<dialog class="sr-dialog" id="fr-new" @if($errors->any()) open @endif>
<form method="POST" action="{{ route('farm-registers.store') }}" class="sr-form">@csrf
    <input type="hidden" name="register" value="{{ $register }}">
    <header><p class="eyebrow">Farm registers</p><h2>New: {{ $meta['label'] }}</h2><button type="button" class="sr-close" data-close-register aria-label="Close">×</button></header>
    <div class="form-grid">
        <div class="field"><label for="fr-date">Date <span class="req">*</span></label><input id="fr-date" type="date" name="entry_date" value="{{ old('entry_date', today()->toDateString()) }}" max="{{ today()->toDateString() }}" required></div>
        @if($register === 'house_preparation')
            <div class="field"><label for="fr-house">House <span class="req">*</span></label><select id="fr-house" name="poultry_house_id" required><option value="">Choose house</option>@foreach($houses as $house)<option value="{{ $house->id }}" @selected(old('poultry_house_id') == $house->id)>{{ $house->house_number }}</option>@endforeach</select></div>
            <div class="field field--wide fr-checks">
                <label>Checks <span class="req">*</span></label>
                @foreach($meta['checks'] as $key => $label)
                    <div class="fr-check"><span>{{ $label }}</span><span class="fr-yesno"><label><input type="radio" name="answers[{{ $key }}]" value="yes" @checked(old("answers.$key") === 'yes') required> Yes</label><label><input type="radio" name="answers[{{ $key }}]" value="no" @checked(old("answers.$key") === 'no')> No</label></span></div>
                @endforeach
            </div>
            <div class="field"><label for="fr-products">Products used (and dilution on the label)</label><input id="fr-products" name="products_used" value="{{ old('products_used') }}"></div>
            <div class="field"><label for="fr-by">Work done by</label><input id="fr-by" name="done_by" value="{{ old('done_by') }}"></div>
            <div class="field field--wide"><label for="fr-notes">Notes</label><textarea id="fr-notes" name="details">{{ old('details') }}</textarea></div>
        @else
            <div class="field"><label for="fr-where">Where <span class="req">*</span></label><input id="fr-where" name="location" value="{{ old('location') }}" required placeholder="e.g. House 4, feed store, borehole"></div>
            @if($register === 'fault')
                <div class="field"><label for="fr-cat">Kind of fault <span class="req">*</span></label><select id="fr-cat" name="category" required><option value="">Choose</option>@foreach($meta['categories'] as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach</select></div>
            @endif
            <div class="field"><label for="fr-sev">How serious <span class="req">*</span></label><select id="fr-sev" name="severity" required><option value="">Choose</option>@foreach($severities as $key => $label)<option value="{{ $key }}" @selected(old('severity') === $key)>{{ $label }}</option>@endforeach</select></div>
            <div class="field field--wide"><label for="fr-what">{{ $register === 'fault' ? 'What is wrong' : 'What happened' }} <span class="req">*</span></label><textarea id="fr-what" name="details" required>{{ old('details') }}</textarea></div>
            <div class="field field--wide"><label for="fr-action">Action taken so far</label><textarea id="fr-action" name="action_taken">{{ old('action_taken') }}</textarea></div>
        @endif
    </div>
    <footer class="form-actions"><button type="button" class="btn btn--quiet" data-close-register>Cancel</button><button class="btn">Save</button></footer>
</form>
</dialog>

<section class="exec-panel table-wrap">
    <table class="exec-table">
        <thead><tr><th>Date</th>@if($register === 'house_preparation')<th>House</th><th>Checks done</th>@else<th>Where</th><th>{{ $register === 'fault' ? 'Fault' : 'What happened' }}</th><th>Seriousness</th>@endif<th>Recorded by</th><th>Status</th>@if($mayClose && $register !== 'house_preparation')<th></th>@endif</tr></thead>
        <tbody>
        @forelse($entries as $entry)
            @php($answers = $entry->answers ? json_decode($entry->answers, true) : [])
            <tr>
                <td>{{ \Carbon\Carbon::parse($entry->entry_date)->format('d M Y') }}</td>
                @if($register === 'house_preparation')
                    @php($done = collect(array_keys($meta['checks']))->filter(fn ($k) => ($answers[$k] ?? null) === 'yes'))
                    <td><strong>{{ $entry->house_number }}</strong></td>
                    <td>{{ $done->count() }} of {{ count($meta['checks']) }}@if($done->count() < count($meta['checks']))<small class="data-sub">Not done: {{ collect($meta['checks'])->except($done->all())->join('; ') }}</small>@endif @if(!empty($answers['products_used']))<small class="data-sub">Products: {{ $answers['products_used'] }}</small>@endif</td>
                @else
                    <td>{{ $entry->location }}</td>
                    <td>@if($register === 'fault' && !empty($answers['category']))<strong>{{ $meta['categories'][$answers['category']] ?? $answers['category'] }}</strong><br>@endif{{ $entry->details }}@if($entry->action_taken)<small class="data-sub">Action: {{ $entry->action_taken }}</small>@endif @if($entry->closing_note)<small class="data-sub">Closed: {{ $entry->closing_note }} ({{ $entry->closer }})</small>@endif</td>
                    <td>{{ $severities[$entry->severity] ?? '—' }}</td>
                @endif
                <td>{{ $entry->recorder }}</td>
                <td><x-status-pill :status="$statusLabel[$entry->status][1] ?? 'pending'" :label="$statusLabel[$entry->status][0] ?? ucfirst($entry->status)" /></td>
                @if($mayClose && $register !== 'house_preparation')
                    <td>@if($entry->status === 'open')<form method="POST" action="{{ route('farm-registers.close', $entry->id) }}" class="inv-decide">@csrf<input name="closing_note" placeholder="What was done" aria-label="What was done" required><button class="btn btn--small">Close</button></form>@endif</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="7">Nothing recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</section>

@if($register === 'fault')
<section class="exec-panel table-wrap">
    <div class="section-heading"><div><h3>Problems reported by workers</h3><p>From the worker's Problem card. The supervisor checks them in Daily Collection Approvals.</p></div></div>
    <table class="exec-table"><thead><tr><th>Date</th><th>House</th><th>Problem</th><th>Reported by</th><th>Status</th></tr></thead><tbody>
    @forelse($workerProblems as $p)
        <tr><td>{{ \Carbon\Carbon::parse($p->reported_on)->format('d M Y') }}</td><td>{{ $p->house_number }}</td><td><strong>{{ $problemLabels[$p->category] ?? $p->category }}</strong><br>{{ $p->details }}</td><td>{{ $p->recorder }}</td><td>{{ ['submitted' => 'Waiting for supervisor', 'approved' => 'Checked', 'returned' => 'Returned'][$p->verification_status] ?? $p->verification_status }}</td></tr>
    @empty
        <tr><td colspan="5">No problems reported by workers.</td></tr>
    @endforelse
    </tbody></table>
</section>
@endif

@push('styles')<style>
.fr-tabs{margin-bottom:8px}
.fr-checks{display:grid;gap:6px}
.fr-check{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:8px 10px;border:1px solid var(--fh-border,#e5dcd8);border-radius:8px;background:var(--fh-glass-strong,#fff)}
.fr-yesno{display:flex;gap:14px;white-space:nowrap}.fr-yesno label{display:flex;align-items:center;gap:5px;font-weight:600}
.sr-dialog{width:min(760px,calc(100vw - 32px));max-height:calc(100vh - 48px);padding:0;border:0;border-radius:16px;box-shadow:0 18px 48px rgba(30,20,20,.25)}
.sr-dialog::backdrop{background:rgba(30,22,25,.45)}
.sr-form{display:grid;gap:12px;padding:20px 22px}
.sr-form header{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between}.sr-form header .eyebrow{flex-basis:100%;margin:0}.sr-form h2{margin:0}
.sr-close{border:0;background:none;font-size:1.5rem;cursor:pointer;line-height:1}
.inv-count{display:inline-grid;place-items:center;min-width:18px;height:18px;margin-left:4px;padding:0 5px;border-radius:99px;background:var(--fh-danger,#c0392b);color:#fff;font-size:.68rem;font-weight:700}
.inv-decide{display:flex;flex-wrap:wrap;gap:6px;align-items:center}.inv-decide input{min-width:150px;padding:6px 8px;border:1px solid var(--fh-border,#ddd);border-radius:8px}
</style>@endpush
<script>
(function () {
    var dialog = document.getElementById('fr-new');
    document.querySelectorAll('[data-open-register]').forEach(function (b) { b.addEventListener('click', function () { dialog.showModal ? dialog.showModal() : dialog.setAttribute('open', ''); }); });
    document.querySelectorAll('[data-close-register]').forEach(function (b) { b.addEventListener('click', function () { dialog.close ? dialog.close() : dialog.removeAttribute('open'); }); });
})();
</script>
@endsection
