@extends('layouts.app')

@section('title', $screen['title'])

@section('content')
    <div class="page-head">
        <h2>New {{ $screen['singular'] }}</h2>
        <p>Record it against the employee so the full history stays together.</p>
    </div>

    <form method="POST" action="{{ route('hr.records.store', $module) }}" enctype="multipart/form-data">
        @csrf
        <div class="card">
            <div class="form-grid">
                <div class="field @error('employee_id') field--error @enderror">
                    <label for="employee_id">Employee <span class="req">required</span></label>
                    <select id="employee_id" name="employee_id" required>
                        <option value="">Choose employee</option>
                        @foreach ($employees as $employee)
                            <option value="{{ $employee->id }}" @selected(old('employee_id') == $employee->id)>
                                {{ $employee->staff_no }} — {{ $employee->fullName() }}
                            </option>
                        @endforeach
                    </select>
                    @error('employee_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="field @error('recorded_on') field--error @enderror">
                    <label for="recorded_on">{{ $module === 'contracts' ? 'Contract start date' : 'Date' }} <span class="req">required</span></label>
                    <input id="recorded_on" name="recorded_on" type="date" value="{{ old('recorded_on', now()->format('Y-m-d')) }}" required>
                    @error('recorded_on')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                @if (in_array($module, ['leave', 'contracts'], true))
                    <div class="field @error('ends_on') field--error @enderror">
                        <label for="ends_on">{{ $module === 'contracts' ? 'Contract end date' : 'End date' }}</label>
                        <input id="ends_on" name="ends_on" type="date" value="{{ old('ends_on') }}">
                        @error('ends_on')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if ($module === 'contracts')
                    <div class="field @error('probation_ends_on') field--error @enderror">
                        <label for="probation_ends_on">Probation end date</label>
                        <input id="probation_ends_on" name="probation_ends_on" type="date" value="{{ old('probation_ends_on') }}">
                        @error('probation_ends_on')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field @error('notice_period_days') field--error @enderror">
                        <label for="notice_period_days">Notice period (days)</label>
                        <input id="notice_period_days" name="notice_period_days" type="number" min="0" max="365" value="{{ old('notice_period_days') }}">
                        @error('notice_period_days')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field @error('signed_on') field--error @enderror">
                        <label for="signed_on">Date signed</label>
                        <input id="signed_on" name="signed_on" type="date" value="{{ old('signed_on') }}">
                        @error('signed_on')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field field--wide @error('document') field--error @enderror">
                        <label for="document">Signed contract document</label>
                        <input id="document" name="document" type="file" accept=".pdf,image/jpeg,image/png">
                        <p class="field-hint">PDF, JPG or PNG, up to 10 MB.</p>
                        @error('document')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if ($module === 'welfare')
                    <div class="field @error('category') field--error @enderror">
                        <label for="category">Welfare type <span class="req">required</span></label>
                        <select id="category" name="category" required>
                            <option value="">Choose type</option>
                            @foreach ([
                                'financial-assistance' => 'Financial assistance',
                                'emergency' => 'Emergency support',
                                'bereavement' => 'Bereavement support',
                                'medical' => 'Medical support',
                                'transport' => 'Transport support',
                                'other' => 'Other',
                            ] as $value => $label)
                                <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('category')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field @error('amount') field--error @enderror">
                        <label for="amount">Support amount (UGX)</label>
                        <input id="amount" name="amount" type="number" min="0" step="1" value="{{ old('amount') }}">
                        <p class="field-hint">Leave blank for non-cash support.</p>
                        @error('amount')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if ($module === 'training')
                    <div class="field @error('title') field--error @enderror">
                        <label for="title">Training title <span class="req">required</span></label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" required>
                        @error('title')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field @error('provider') field--error @enderror">
                        <label for="provider">Provider or trainer</label>
                        <input id="provider" name="provider" type="text" value="{{ old('provider') }}">
                        @error('provider')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if ($module === 'staff-performance')
                    <div class="field @error('title') field--error @enderror">
                        <label for="title">Review period <span class="req">required</span></label>
                        <input id="title" name="title" type="text" placeholder="For example: July 2026" value="{{ old('title') }}" required>
                        @error('title')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="field @error('score') field--error @enderror">
                        <label for="score">Performance score (0–100)</label>
                        <input id="score" name="score" type="number" min="0" max="100" step="0.01" value="{{ old('score') }}">
                        @error('score')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if (in_array($module, ['advances', 'discipline'], true))
                    <div class="field @error('amount') field--error @enderror">
                        <label for="amount">Amount (UGX) {{ $module === 'discipline' ? '' : '' }}</label>
                        <input id="amount" name="amount" type="number" min="0" step="1" value="{{ old('amount') }}">
                        @error('amount')<p class="field-error">{{ $message }}</p>@enderror
                    </div>
                @endif

                @if ($module === 'discipline')
                    <div class="field">
                        <label><input name="financial_penalty" type="checkbox" value="1" @checked(old('financial_penalty'))> Financial penalty</label>
                        <p class="field-hint">When approved, the amount is automatically queued as a payroll deduction.</p>
                    </div>
                @endif

                <div class="field field--wide @error('details') field--error @enderror">
                    <label for="details">{{ $module === 'contracts' ? 'Special terms or notes' : ($module === 'welfare' ? 'Reason and support given' : ($module === 'training' ? 'Training outcome or notes' : ($module === 'staff-performance' ? 'Reviewer comments and goals' : 'Details'))) }} @if ($module !== 'contracts')<span class="req">required</span>@endif</label>
                    <textarea id="details" name="details" rows="5" @if ($module !== 'contracts') required @endif>{{ old('details') }}</textarea>
                    @error('details')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn btn--quiet" href="{{ route('hr.records.index', $module) }}">Cancel</a>
            <button class="btn" type="submit">Save record</button>
        </div>
    </form>
@endsection
