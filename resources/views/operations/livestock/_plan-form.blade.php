{{-- Fields for a goat / rabbit / dog vaccination schedule item. $plan is null when adding. --}}
@php $fid = $plan ? 'plan'.$plan->id : 'plan-new'; @endphp
<div class="form-grid">
    <div class="field"><label for="{{ $fid }}-vaccine">Vaccine</label><input id="{{ $fid }}-vaccine" name="vaccine" value="{{ $plan->vaccine ?? '' }}" required maxlength="180" placeholder="e.g. PPR, Rabies"></div>
    <div class="field"><label for="{{ $fid }}-group">Group</label><select id="{{ $fid }}-group" name="livestock_group_id"><option value="">All groups</option>@foreach($groups as $g)<option value="{{ $g->id }}" @selected(($plan->livestock_group_id ?? null) == $g->id)>{{ $g->group_code }}</option>@endforeach</select></div>
    <div class="field"><label for="{{ $fid }}-first">First due on</label><input id="{{ $fid }}-first" type="date" name="first_due_on" value="{{ $plan->first_due_on ?? now()->toDateString() }}" required></div>
    <div class="field"><label for="{{ $fid }}-repeat">Repeat every (months)</label><input id="{{ $fid }}-repeat" type="number" min="1" max="60" name="repeat_every_months" value="{{ $plan->repeat_every_months ?? '' }}" placeholder="Leave empty if given once"></div>
    <div class="field field--wide"><label for="{{ $fid }}-note">Note</label><input id="{{ $fid }}-note" name="note" value="{{ $plan->note ?? '' }}" maxlength="1000"></div>
    <div class="field"><label><input type="checkbox" name="is_essential" value="1" @checked($plan->is_essential ?? true)> Essential</label></div>
    @if($plan)<input type="hidden" name="_editing" value="1"><div class="field"><label><input type="checkbox" name="is_active" value="1" @checked($plan->is_active)> Active</label></div>@endif
</div>
<p class="field-hint">Once given, the next dose is due the set number of months after the last dose.</p>
