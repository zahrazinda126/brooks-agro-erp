{{-- Goat / rabbit / dog vaccination schedule: one row per schedule item and group, with when it is next due. --}}
@php
    $scheduleRows = request('group') ? $schedule->filter(fn ($r) => $r['group'] && $r['group']->id == request('group'))->values() : $schedule;
    $pill = ['overdue' => ['overdue', 'Overdue'], 'due' => ['pending', 'Due soon'], 'scheduled' => ['scheduled', 'Scheduled'], 'done' => ['completed', 'Given'], 'inactive' => ['inactive', 'Inactive'], 'no-group' => ['inactive', 'No active group']];
@endphp
<div class="card table-wrap">
<table>
    <thead><tr><th>Vaccine</th><th>Group</th><th>Repeats</th><th>Last given</th><th>Next due</th><th>Status</th><th></th></tr></thead>
    <tbody>
    @forelse($scheduleRows as $row)
        @php $plan = $row['plan']; @endphp
        <tr>
            <td><strong>{{ $plan->vaccine }}</strong>@if($plan->is_essential)<small class="table-sub">Essential</small>@endif @if($plan->note)<small class="table-sub">{{ $plan->note }}</small>@endif</td>
            <td>{{ $row['group']->group_code ?? '—' }}</td>
            <td>{{ $plan->repeat_every_months ? 'Every '.$plan->repeat_every_months.' months' : 'Once' }}</td>
            <td>{{ $row['last']?->format('d M Y') ?? 'Not yet' }}</td>
            <td>{{ $row['next']?->format('d M Y') ?? '—' }}</td>
            <td><x-status-pill :status="$pill[$row['status']][0]" :label="$pill[$row['status']][1]" /></td>
            <td>@if($canRecord && ($loop->first || $scheduleRows[$loop->index - 1]['plan']->id !== $plan->id))
                <button type="button" class="btn btn--quiet btn--small" onclick="document.getElementById('edit-plan-{{ $plan->id }}').showModal()">Edit</button>
                <dialog id="edit-plan-{{ $plan->id }}" class="page-dialog" aria-label="Edit schedule item"><button type="button" class="page-dialog__close" aria-label="Close" onclick="this.closest('dialog').close()">&times;</button><h3>Edit schedule item</h3>
                    <form method="POST" action="{{ route('operations.livestock.plans.update', ['unit' => $unit, 'plan' => $plan->id]) }}">@csrf @method('PUT')<input type="hidden" name="_dialog" value="edit-plan-{{ $plan->id }}">@include('operations.livestock._plan-form', ['plan' => $plan])<div class="form-actions"><button class="btn">Save changes</button></div></form>
                </dialog>
            @endif</td>
        </tr>
    @empty
        <tr><td colspan="7" class="muted">No schedule items.</td></tr>
    @endforelse
    </tbody>
</table>
</div>
<script>
(() => {
    // Reopen the form after a failed save.
    const open = @json(old('_dialog'));
    const dialog = open && document.getElementById(open);
    if (dialog && dialog.showModal) dialog.showModal();
})();
</script>
