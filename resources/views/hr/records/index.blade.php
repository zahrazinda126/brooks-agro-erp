@extends('layouts.app')

@section('title', $screen['title'])

@section('content')
    <div class="page-head page-head--split">
        <div>
            <h2>{{ $screen['title'] }}</h2>
            <p>{{ $records->total() }} {{ Str::plural($screen['singular'], $records->total()) }}</p>
        </div>
        @if ($user->canDo($module, 'create'))
            <a class="btn btn--inline" href="{{ route('hr.records.create', $module) }}">Add record</a>
        @endif
    </div>

    @if ($records->isEmpty())
        <div class="card empty">
            <p class="card-label">Nothing recorded</p>
            <p class="muted" style="margin:0;">Create the first {{ $screen['singular'] }} when it is needed.</p>
        </div>
    @else
        <div class="data-list">
            <div class="data-head">
                <span>Date</span><span>Employee</span><span>Details</span><span>Status</span><span>Amount</span>
            </div>
            @foreach ($records as $record)
                <div class="data-row">
                    <span class="data-cell" data-label="Date">{{ $record->recorded_on->format('j M Y') }}</span>
                    <span class="data-cell data-cell--strong" data-label="Employee">
                        {{ $record->employee->fullName() }}
                        <em class="data-sub mono">{{ $record->employee->staff_no }}</em>
                    </span>
                    <span class="data-cell" data-label="Details">
                        {{ Str::limit($record->details, 90) }}
                        @if ($module === 'contracts' && $record->attachments->isNotEmpty())
                            <a href="{{ route('hr.records.attachments.download', [$module, $record, $record->attachments->first()]) }}">View document</a>
                        @endif
                    </span>
                    <span class="data-cell" data-label="Status">
                        <span class="pill {{ $record->status === 'pending' ? 'pill--muted' : '' }}">{{ ucfirst($record->status) }}</span>
                        @if ($record->is_payroll_deduction && $record->status === 'pending' && $user->canDo($module, 'approve'))
                            <form method="POST" action="{{ route('hr.records.approve', [$module, $record]) }}" style="display:inline;">
                                @csrf
                                <button class="btn btn--quiet btn--inline" type="submit">Approve deduction</button>
                            </form>
                        @endif
                    </span>
                    <span class="data-cell" data-label="Amount">{{ $record->amount ? 'UGX '.number_format($record->amount) : '—' }}</span>
                </div>
            @endforeach
        </div>
        <div class="pager">{{ $records->links() }}</div>
    @endif
@endsection
