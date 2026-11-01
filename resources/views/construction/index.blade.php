@extends('layouts.app')
@section('title', 'Construction')
@section('content')
@if($canManage)
<div class="screen-actions"><button type="button" class="btn btn--small" onclick="document.getElementById('new-project').showModal()">+ New project</button></div>
<dialog id="new-project" class="page-dialog" aria-label="New project"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button><h3>New construction project</h3>
<form method="POST" action="{{ route('construction.store') }}">@csrf<input type="hidden" name="_dialog" value="new-project">
    <div class="form-grid">
        <div class="field field--wide"><label for="cp-name">Project</label><input id="cp-name" name="name" value="{{ old('name') }}" required maxlength="160" placeholder="e.g. New poultry house 3"></div>
        <div class="field"><label for="cp-loc">Location</label><input id="cp-loc" name="location" value="{{ old('location') }}" maxlength="160"></div>
        <div class="field"><label for="cp-start">Start date</label><input id="cp-start" type="date" name="starts_on" value="{{ old('starts_on', now()->toDateString()) }}" required></div>
        <div class="field"><label for="cp-end">Expected finish</label><input id="cp-end" type="date" name="expected_end_on" value="{{ old('expected_end_on') }}"></div>
        <div class="field field--wide"><label for="cp-notes">Notes</label><textarea id="cp-notes" name="notes" maxlength="2000">{{ old('notes') }}</textarea></div>
    </div>
    <h4 class="u-mt-12">Lead constructor</h4>
    <div class="choice-row" role="radiogroup" aria-label="Lead constructor">
        <label class="check-label"><input type="radio" name="person" value="none" @checked(old('person', 'none') === 'none')> Not yet</label>
        <label class="check-label"><input type="radio" name="person" value="existing" @checked(old('person') === 'existing')> Already registered</label>
        <label class="check-label"><input type="radio" name="person" value="new" @checked(old('person') === 'new')> New person</label>
    </div>
    <div class="form-grid u-mt-12">
        <div class="field field--wide" data-person="existing"><label for="lp-person">Person</label><select id="lp-person" name="employee_id"><option value="">Choose a registered person</option>@foreach($people as $person)<option value="{{ $person->id }}" @selected(old('employee_id') == $person->id)>{{ $person->first_name }} {{ $person->last_name }} · {{ $person->staff_no }}{{ $person->on_payroll ? '' : ' · temporary' }}</option>@endforeach</select></div>
        <div class="field" data-person="new"><label for="lp-first">First name</label><input id="lp-first" name="new_first_name" value="{{ old('new_first_name') }}" maxlength="80"></div>
        <div class="field" data-person="new"><label for="lp-last">Surname</label><input id="lp-last" name="new_last_name" value="{{ old('new_last_name') }}" maxlength="80"></div>
        <div class="field" data-person="new"><label for="lp-phone">Phone</label><input id="lp-phone" name="new_phone" type="tel" value="{{ old('new_phone') }}" maxlength="30"></div>
        <div class="field" data-person="new"><label for="lp-nin">National ID <small>(optional)</small></label><input id="lp-nin" name="new_national_id" value="{{ old('new_national_id') }}" maxlength="20"></div>
        <input type="hidden" name="new_role" value="Lead constructor" data-person="new"><input type="hidden" name="new_arrived_on" value="{{ now()->toDateString() }}" data-person="new">
        <div class="field" data-person="any"><label for="lp-basis">Paid</label><select id="lp-basis" name="pay_basis"><option value="job" @selected(old('pay_basis') !== 'day')>For the whole job</option><option value="day" @selected(old('pay_basis') === 'day')>Per day</option></select></div>
        <div class="field" data-person="any"><label for="lp-amount">Agreed amount (UGX)</label><input id="lp-amount" type="number" min="0" step="1" name="agreed_amount" value="{{ old('agreed_amount') }}"></div>
    </div>
    <p class="field-hint">The farm pays the lead constructor; the lead pays the team. Add the team on the project page.</p>
    <div class="form-actions"><button class="btn">Create project</button></div>
</form></dialog>
@endif
@if($errors->any())<div class="notice notice--alarm">{{ $errors->first() }}</div>@endif

<div class="card table-wrap">
<table class="exec-table">
    <thead><tr><th>Project</th><th>Location</th><th>Started</th><th>Expected finish</th><th class="num">Builders</th><th class="num">Materials issued (UGX)</th><th class="num">Paid (UGX)</th><th class="num">Owed to suppliers (UGX)</th><th>Status</th></tr></thead>
    <tbody>
    @forelse($projects as $p)
        <tr>
            <td><a href="{{ route('construction.show', $p->id) }}"><strong>{{ $p->name }}</strong></a></td>
            <td>{{ $p->location ?: '—' }}</td>
            <td>{{ \Carbon\Carbon::parse($p->starts_on)->format('d M Y') }}</td>
            <td>{{ $p->expected_end_on ? \Carbon\Carbon::parse($p->expected_end_on)->format('d M Y') : '—' }}</td>
            <td class="num">{{ $p->builders }}</td>
            <td class="num">{{ number_format($p->materials_value) }}</td>
            <td class="num">{{ number_format($p->paid) }}</td>
            <td class="num">{{ number_format($p->owed) }}</td>
            <td><x-status-pill :status="$p->status === 'finished' ? 'completed' : 'active'" :label="$p->status === 'finished' ? 'Finished' : 'In progress'" /></td>
        </tr>
    @empty
        <tr><td colspan="9" class="muted">No construction projects yet.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<script>(() => { const o = @json(old('_dialog')); const d = o && document.getElementById(o); if (d && d.showModal) d.showModal(); })();</script>
<script>
(() => {
    // Show only the lead-constructor fields that apply.
    const form = document.querySelector('#new-project form'); if (!form) return;
    const sync = () => {
        const who = (form.querySelector('input[name="person"]:checked') || {}).value || 'none';
        form.querySelectorAll('[data-person]').forEach(el => {
            const show = el.dataset.person === 'any' ? who !== 'none' : el.dataset.person === who;
            if (el.tagName !== 'INPUT') el.hidden = !show;
            (el.matches('input,select') ? [el] : el.querySelectorAll('input,select')).forEach(i => { i.disabled = !show; });
        });
        ['lp-first', 'lp-last', 'lp-phone'].forEach(id => { form.querySelector('#' + id).required = who === 'new'; });
        form.querySelector('#lp-person').required = who === 'existing';
        form.querySelector('#lp-amount').required = who !== 'none';
    };
    form.addEventListener('change', sync); sync();
})();
</script>
@endsection
