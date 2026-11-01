@extends('layouts.app')
@section('title', $unitName.' death examination')
@section('content')
@include('partials.monitor-group', ['group' => 'health', 'current' => 'examination'])

<section class="u-mt-12">
<h3>{{ rtrim(\App\Http\Controllers\Poultry\HealthMonitorController::UNITS[$unit], 's') }} deaths to examine</h3>
<div class="card table-wrap">
<table>
    <thead><tr><th>Date</th><th>Group</th><th>Lost</th><th>Suspected cause</th><th>Recorded by</th><th></th></tr></thead>
    <tbody>
    @forelse($waiting as $death)
        <tr>
            <td>{{ \Carbon\Carbon::parse($death->occurred_on)->format('d M Y') }}</td><td>{{ $death->group_code }}</td><td>{{ number_format($death->quantity) }}</td>
            <td>{{ $death->suspected_cause ?: '—' }}@if($death->notes)<small class="table-sub">{{ $death->notes }}</small>@endif</td><td>{{ $death->recorder_name ?? '—' }}</td>
            <td>
                <button type="button" class="btn btn--small" onclick="document.getElementById('examine-{{ $death->id }}').showModal()">Examine</button>
                <dialog id="examine-{{ $death->id }}" class="page-dialog" aria-label="Examine death"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button>
                    <h3>{{ $death->group_code }} · {{ number_format($death->quantity) }} lost on {{ \Carbon\Carbon::parse($death->occurred_on)->format('d M Y') }}</h3>
                    <form method="POST" action="{{ route('operations.livestock.examine.update', ['unit' => $unit, 'death' => $death->id]) }}">@csrf @method('PUT')<input type="hidden" name="_dialog" value="examine-{{ $death->id }}">
                        <div class="form-grid">
                            <div class="field field--wide"><label for="cause-{{ $death->id }}">What caused it</label><input id="cause-{{ $death->id }}" name="established_cause" value="{{ old('_dialog') === 'examine-'.$death->id ? old('established_cause') : $death->suspected_cause }}" required maxlength="160" placeholder="If unknown, write: Cause not established">@if(old('_dialog') === 'examine-'.$death->id)@error('established_cause')<p class="field-error">{{ $message }}</p>@enderror @endif</div>
                            <div class="field field--wide"><label for="note-{{ $death->id }}">Findings</label><textarea id="note-{{ $death->id }}" name="assessment_note" maxlength="2000"></textarea></div>
                            <div class="field field--wide"><label for="action-{{ $death->id }}">Action taken</label><textarea id="action-{{ $death->id }}" name="action_taken" maxlength="2000"></textarea></div>
                            <div class="field"><label><input type="checkbox" name="vet_called" value="1" onchange="this.form.vet_name.closest('.field').hidden = !this.checked"> A vet was called</label></div>
                            <div class="field" hidden><label for="vet-{{ $death->id }}">Vet's name</label><input id="vet-{{ $death->id }}" name="vet_name" maxlength="160"></div>
                        </div>
                        <div class="form-actions"><button class="btn">Save examination</button></div>
                    </form>
                </dialog>
            </td>
        </tr>
    @empty
        <tr><td colspan="6" class="muted">Nothing waiting. Every recorded death has been examined.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
</section>

@if($examined->isNotEmpty())
<section class="u-mt-12">
<h3>Recently examined</h3>
<div class="card table-wrap">
<table>
    <thead><tr><th>Date</th><th>Group</th><th>Lost</th><th>Cause</th><th>Action taken</th><th>Vet</th><th>Examined by</th></tr></thead>
    <tbody>
    @foreach($examined as $death)
        <tr><td>{{ \Carbon\Carbon::parse($death->occurred_on)->format('d M Y') }}</td><td>{{ $death->group_code }}</td><td>{{ number_format($death->quantity) }}</td><td>{{ $death->established_cause ?: '—' }}@if($death->assessment_note)<small class="table-sub">{{ $death->assessment_note }}</small>@endif</td><td>{{ $death->action_taken ?: '—' }}</td><td>{{ $death->vet_called ? ($death->vet_name ?: 'Yes') : 'No' }}</td><td>{{ $death->assessor_name ?? 'Before examination started' }}</td></tr>
    @endforeach
    </tbody>
</table>
</div>
</section>
@endif
<script>
(() => {
    // Reopen the form after a failed save.
    const open = @json(old('_dialog'));
    const dialog = open && document.getElementById(open);
    if (dialog && dialog.showModal) dialog.showModal();
})();
</script>
@endsection
