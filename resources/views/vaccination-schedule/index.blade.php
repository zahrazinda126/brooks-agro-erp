@extends('layouts.app')
@section('title','Vaccination Schedule')
@section('content')
<x-report-header eyebrow="Poultry health" :title="$view === 'records' ? 'Vaccinations Given' : 'Vaccination Schedule'" :description="$view === 'records' ? 'Vaccinations administered by batch, with lot, expiry and dose traceability.' : 'The vaccination programme by bird age, per batch and general.'" period="Current programme and history"><x-slot:actions><button class="btn btn--quiet" type="button" onclick="window.print()">Print</button></x-slot:actions></x-report-header>
@include('partials.health-switch', ['healthCurrent' => 'vaccination'])
@if($canEdit)<div class="screen-actions"><button type="button" class="btn btn--small" onclick="document.getElementById('add-schedule').showModal()">+ Add schedule item</button><button type="button" class="btn btn--small" onclick="document.getElementById('record-vaccination').showModal()">+ Record vaccination</button></div>@endif
@if($canEdit)
<dialog id="record-vaccination" class="page-dialog" aria-label="Record vaccination"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button><h3>Record vaccination completed</h3><form method="POST" action="{{ route('vaccination-records.store') }}">@csrf<input type="hidden" name="_dialog" value="record">
<div class="form-grid">
<div class="field"><label>Poultry batch</label><select name="animal_group_id" required><option value="">Choose batch</option>@foreach($batches as $batch)<option value="{{ $batch->id }}" @selected(old('animal_group_id',request('batch'))==$batch->id)>{{ $batch->code }} &mdash; {{ $batch->name ?: $batch->house }} ({{ number_format($batch->current_quantity) }} birds)</option>@endforeach</select></div>
<div class="field"><label>Vaccine used</label><select name="medication_id" required><option value="">Choose vaccine</option>@foreach($medications as $vaccine)<option value="{{ $vaccine->id }}" @selected(old('medication_id',request('vaccine'))==$vaccine->id)>{{ $vaccine->name }}</option>@endforeach</select></div>
<div class="field"><label>Date administered</label><input type="date" name="occurred_on" max="{{ today()->toDateString() }}" value="{{ old('occurred_on',today()->toDateString()) }}" required></div>
<div class="field"><label>Birds vaccinated</label><input type="number" name="birds_treated" min="1" value="{{ old('birds_treated') }}" required></div>
<div class="field"><label>Dose given</label><input name="dose_given" value="{{ old('dose_given') }}" placeholder="e.g. 1 dose per bird / 20 ml" required></div>
<div class="field"><label>Application route</label><input name="application_route" value="{{ old('application_route') }}" placeholder="Drinking water, eye drop, spray"></div>
<div class="field"><label>Vaccine batch / lot number</label><input name="vaccine_batch_number" value="{{ old('vaccine_batch_number') }}" required></div>
<div class="field"><label>Manufacturer</label><input name="manufacturer" value="{{ old('manufacturer') }}"></div>
<div class="field"><label>Manufacture date</label><input type="date" name="manufactured_on" value="{{ old('manufactured_on') }}"></div>
<div class="field"><label>Expiry date</label><input type="date" name="expires_on" value="{{ old('expires_on') }}" required></div>
<div class="field"><label>Next vaccination date</label><input type="date" name="next_due_on" value="{{ old('next_due_on') }}"></div>
<div class="field"><label>Administered by</label><input name="administered_name" value="{{ old('administered_name',$user->displayName()) }}"></div>
<div class="field"><label>Cost (UGX)</label><input type="number" min="0" name="cost" value="{{ old('cost') }}"></div>
<div class="field"><label class="check"><input type="checkbox" name="vet_involved" value="1" @checked(old('vet_involved'))><span>Veterinary professional involved</span></label></div>
<div class="field field--wide"><label>Observations / notes</label><textarea name="findings">{{ old('findings') }}</textarea></div>
</div><button class="btn">Save vaccination record</button></form></dialog>
@endif
@if($canEdit)<dialog id="add-schedule" class="page-dialog" aria-label="Add schedule item"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button><h3>Add schedule item</h3><form method="POST" action="{{ route('vaccination-schedule.store') }}">@csrf<input type="hidden" name="_dialog" value="schedule"> @include('vaccination-schedule._form',['plan'=>null])<button class="btn">Add to schedule</button></form></dialog>@endif
@include('vaccination-schedule._filters')
@if($view === 'records')
<div class="card table-wrap"><table><thead><tr><th>Date</th><th>Batch</th><th>Vaccine</th><th>Birds</th><th>Dose / route</th><th>Vaccine lot</th><th>Manufacturer / expiry</th><th>Next due</th><th>Recorded by</th></tr></thead><tbody>@forelse($records as $record)<tr><td>{{ $record->occurred_on->format('d M Y') }}</td><td><strong>{{ $record->batch?->code }}</strong><small>{{ $record->batch?->breed?->name }}</small></td><td>{{ $record->medication?->name }}</td><td>{{ number_format($record->birds_treated) }}</td><td>{{ $record->dose_given }}<small>{{ $record->application_route }}</small></td><td>{{ $record->vaccine_batch_number ?: 'Not recorded' }}</td><td>{{ $record->manufacturer ?: 'Not recorded' }}<small>Expires {{ $record->expires_on?->format('d M Y') ?: 'not recorded' }}</small></td><td>{{ $record->next_due_on?->format('d M Y') ?: 'Not set' }}</td><td>{{ $record->recorder?->displayName() }}</td></tr>@empty<tr><td colspan="9">No completed vaccinations match these filters.</td></tr>@endforelse</tbody></table></div>{{ $records->links() }}
@else

<div class="card table-wrap vaccination-table"><table><thead><tr><th>Batch</th><th>Purpose</th><th>Vaccine</th><th>Breed</th><th>First due</th><th>Window</th><th>Repeats</th><th>Essential</th><th>Status</th><th></th></tr></thead><tbody>@forelse($plans as $plan)<tr><td>{{ $plan->batch?->code ?? 'General programme' }}</td><td>{{ Str::headline($plan->purpose) }}</td><td><strong>{{ $plan->medication?->name }}</strong><small>{{ $plan->note }}</small></td><td>{{ $plan->breed?->name ?? 'All breeds' }}</td><td>{{ $plan->ageLabel() }}</td><td>&plusmn; {{ $plan->window_days }} days</td><td>{{ $plan->repeat_every_days ? 'Every '.$plan->repeat_every_days.' days until day '.($plan->repeat_until_days?:$plan->age_days+365) : 'Once' }}</td><td>{{ $plan->is_essential?'Yes':'No' }}</td><td>@if($plan->is_finished)<x-status-pill status="completed" label="Finished" />@else<x-status-pill :status="$plan->is_active ? 'pending' : 'inactive'" :label="$plan->is_active ? 'Active' : 'Inactive'" />@endif @if($plan->doses_given && !$plan->is_finished)<small class="table-sub">{{ $plan->doses_given }} of {{ count($plan->dueAges()) }} doses given</small>@endif</td><td>@if($canEdit)<button type="button" class="btn btn--quiet btn--small" onclick="document.getElementById('edit-plan-{{ $plan->id }}').showModal()">Edit</button><dialog id="edit-plan-{{ $plan->id }}" class="page-dialog" aria-label="Edit schedule item"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button><h3>Edit schedule item</h3><form method="POST" action="{{ route('vaccination-schedule.update',$plan) }}">@csrf @method('PUT')<input type="hidden" name="_dialog" value="edit-plan-{{ $plan->id }}"> @include('vaccination-schedule._form',['plan'=>$plan])<div class="form-actions"><button class="btn">Save changes</button></div></form></dialog>@endif</td></tr>@empty<tr><td colspan="10">No schedule items match these filters.</td></tr>@endforelse</tbody></table></div>
<script>
document.querySelectorAll('[data-schedule-batch]').forEach(select => {
    const details = select.closest('form').querySelector('[data-schedule-details]');
    const sync = () => {
        const option = select.selectedOptions[0];
        details.textContent = option?.value ? option.dataset.breed + ' / ' + option.dataset.purpose : details.dataset.default;
    };
    select.addEventListener('change', sync);
    sync();
});
@endif
</script>
<script>
(() => {
    // Reopen the form after a failed save, or when a reminder link pre-selects a batch.
    const open = @json(old('_dialog') ?: (request()->filled('batch') && $view === 'records' ? 'record' : null));
    const dialog = open && document.getElementById(open === 'record' ? 'record-vaccination' : (open === 'schedule' ? 'add-schedule' : open));
    if (dialog && dialog.showModal) dialog.showModal();
})();
</script>
@endsection
