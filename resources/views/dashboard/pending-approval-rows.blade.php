<section class="finance-chart-card finance-recent">
    <div class="finance-panel-head">
        <div>
            <h3>Pending approvals</h3>
            <p>Showing the five oldest requests waiting for {{ $approvalAudience }} review.</p>
        </div>
        <a class="btn btn--quiet btn--inline" href="{{ route('approvals.index') }}">View all {{ $approvalCount }}</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead><tr><th>Request</th><th>Party / details</th><th>Items / purpose</th><th>Payment / reference</th><th>Submitted by</th><th>Amount</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse($approvalRows as $approval)
                    @php
                        $approvable = $approval->approvable;
                        if ($approvable instanceof \App\Models\Sale) {
                            $approvable->loadMissing(['lines', 'payments']);
                        }
                    @endphp
                    <tr>
                        <td><strong>{{ $approval->label() }}</strong><small class="table-sub">{{ Str::headline($approval->module_key) }} &middot; {{ $approval->submitted_at?->format('d M Y, H:i') ?? $approval->created_at->format('d M Y, H:i') }}</small></td>
                        <td>
                            @if($approvable instanceof \App\Models\Sale)
                                {{ $approvable->is_staff_consumption ? ($approvable->consumed_by ?: 'Internal farm use') : $approvable->customer_name }}
                            @elseif($approvable instanceof \App\Models\Purchase)
                                {{ $approvable->supplier_name }}
                            @else
                                {{ $approval->submission_note ?: 'No additional party details' }}
                            @endif
                        </td>
                        <td>
                            @if($approvable instanceof \App\Models\Sale)
                                @foreach($approvable->lines as $line)
                                    <div><strong>{{ $line->description }}</strong> &middot; {{ number_format($line->quantity, 2) }} {{ $line->unit }}</div>
                                @endforeach
                            @elseif($approvable instanceof \App\Models\Purchase)
                                {{ $approvable->item }} &middot; {{ number_format($approvable->quantity, 2) }} {{ $approvable->unit }}
                            @else
                                {{ Str::limit($approval->submission_note ?: $approval->label(), 100) }}
                            @endif
                        </td>
                        <td>
                            @if($approvable instanceof \App\Models\Sale)
                                {{ $approvable->is_staff_consumption ? 'No payment' : Str::headline($approvable->payment_method) }}
                                @if($approvable->payments->first()?->reference)
                                    <small class="table-sub">{{ $approvable->payments->first()->reference }}</small>
                                @endif
                            @else
                                {{ data_get($approvable, 'reference') ?: '—' }}
                            @endif
                        </td>
                        <td>{{ $approval->submitter?->displayName() ?? 'System' }}<small class="table-sub">Due {{ $approval->due_at?->format('d M Y, H:i') ?? 'not set' }}</small></td>
                        <td><strong>{{ $approval->amount() ? 'UGX '.$approval->amount() : '—' }}</strong></td>
                        <td>
                            <div class="form-actions form-actions--flush">
                                <form method="POST" action="{{ route('approvals.decide', $approval) }}">
                                    @csrf
                                    <input type="hidden" name="decision" value="approved">
                                    <button class="btn btn--small">Approve</button>
                                </form>
                                <form method="POST" action="{{ route('approvals.decide', $approval) }}" onsubmit="const reason=prompt('Why are you rejecting this request?');if(!reason)return false;this.elements.decision_note.value=reason;">
                                    @csrf
                                    <input type="hidden" name="decision" value="rejected">
                                    <input type="hidden" name="decision_note">
                                    <button class="btn btn--quiet btn--small">Reject</button>
                                </form>
                                <a class="btn btn--quiet btn--small" href="{{ route('approvals.show', $approval) }}">Details</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7">No approvals are waiting for {{ $approvalAudience }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
