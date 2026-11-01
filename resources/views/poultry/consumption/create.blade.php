@extends('layouts.app')

@section('title', 'Record birds taken')

@section('content')

<div class="page-head">
    <h2>Record birds taken</h2>
    <p>Birds slaughtered for the farm's own use rather than sold.</p>
</div>

@if ($errors->any())
    <div class="notice notice--alarm">{{ $errors->first() }}</div>
@endif

<form method="POST" action="{{ route('consumption.store') }}">
    @csrf

    <div class="card">
        <div class="form-grid">
            <div class="field @error('animal_group_id') field--error @enderror">
                <label for="animal_group_id">Which flock <span class="req">required</span></label>
                <select id="animal_group_id" name="animal_group_id" required>
                    <option value="">Choose a flock</option>
                    @foreach ($batches as $b)
                        <option value="{{ $b->id }}" @selected((int) old('animal_group_id') === $b->id)>
                            {{ $b->code }} — {{ $b->name ?: $b->house }}
                            ({{ number_format($b->current_quantity) }} birds)
                        </option>
                    @endforeach
                </select>
                @error('animal_group_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="field @error('consumed_on') field--error @enderror">
                <label for="consumed_on">Date taken <span class="req">required</span></label>
                <input id="consumed_on" name="consumed_on" type="date"
                       value="{{ old('consumed_on', now()->format('Y-m-d')) }}"
                       max="{{ now()->format('Y-m-d') }}" required>
                @error('consumed_on')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="field @error('quantity') field--error @enderror">
                <label for="quantity">How many birds <span class="req">required</span></label>
                <input id="quantity" name="quantity" type="number"
                       inputmode="numeric" min="1" max="10000"
                       value="{{ old('quantity') }}" required>
                @error('quantity')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="purpose">What for <span class="req">required</span></label>
                <select id="purpose" name="purpose" required>
                    @foreach ([
                        'staff_meals' => 'Staff meals',
                        'management'  => 'Management household',
                        'visitors'    => 'Visitors',
                        'event'       => 'Farm event',
                        'sick_bird'   => 'Sick bird, slaughtered early',
                        'other'       => 'Other',
                    ] as $v => $label)
                        <option value="{{ $v }}" @selected(old('purpose', 'staff_meals') === $v)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field field--wide">
                <label for="detail">Details</label>
                <input id="detail" name="detail" type="text"
                       value="{{ old('detail') }}"
                       placeholder="e.g. end of month staff lunch">
            </div>
        </div>
    </div>

    <div class="card">
        <p class="card-label">Who authorised it <span class="req">required</span></p>
        <p class="muted u-m0-b14">
            A live bird leaving the flock always needs a name against it.
            Without one, "eaten on the farm" is the easiest cover there is for
            a bird walking out of the gate.
        </p>

        <div class="form-grid">
            <div class="field @error('authorised_by') field--error @enderror">
                <label for="authorised_by">Authorised by</label>
                <select id="authorised_by" name="authorised_by">
                    <option value="">Choose a person</option>
                    @foreach ($authorisers as $a)
                        <option value="{{ $a->id }}" @selected((int) old('authorised_by') === $a->id)>
                            {{ $a->displayName() }} — {{ $a->roleSummary() }}
                        </option>
                    @endforeach
                </select>
                @error('authorised_by')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="field">
                <label for="authorised_name">Or write a name</label>
                <input id="authorised_name" name="authorised_name" type="text"
                       value="{{ old('authorised_name') }}"
                       placeholder="If they have no account">
            </div>
        </div>
    </div>

    <div class="card">
        <p class="card-label">What the birds were worth</p>
        <p class="muted u-m0-b14">
            Not money that came in — none did. But the farm gave that up, and
            a year of unvalued birds is a hole in the accounts nobody can see.
        </p>

        <div class="field @error('estimated_value') field--error @enderror">
            <label for="estimated_value">Estimated value (UGX)</label>
            <input id="estimated_value" name="estimated_value" type="number"
                   inputmode="numeric" min="0" step="1"
                   value="{{ old('estimated_value') }}"
                   placeholder="What they would have sold for">
            @error('estimated_value')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>

    <div class="notice">
        Someone else will need to confirm this record. You cannot confirm your
        own.
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn--inline">Save</button>
        <a class="btn btn--quiet btn--inline" href="{{ route('consumption.index') }}">Cancel</a>
    </div>
</form>

@endsection
