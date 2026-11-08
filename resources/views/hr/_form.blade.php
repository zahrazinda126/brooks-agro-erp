{{--
    Shared by create and edit.

    Grouped the way HR actually collects the information: who they are,
    how to reach them, what they do, how they get paid. Only name, phone
    and employment type are required — a casual worker taken on this
    morning can be registered in under a minute and completed later.
--}}

<div class="card">
    <p class="card-label">Who they are</p>

    <div class="form-grid">
        <div class="field @error('staff_no') field--error @enderror">
            <label for="staff_no">Staff number</label>
            <input id="staff_no" name="staff_no" type="text"
                   value="{{ old('staff_no', $employee->staff_no ?? $nextStaffNo ?? '') }}" @if(isset($employee)) required @endif>
            @error('staff_no')<p class="field-error">{{ $message }}</p>@enderror
            @if (! isset($employee))
                <p class="field-hint">
                    Use BAF 001, BAF 002 and so on, or keep the suggested number.
                </p>
            @else
                <p class="field-hint">You can change this number. Use a unique number such as BAF 001. The login username is edited separately under the employee's account.</p>
            @endif
        </div>

        <div class="field @error('first_name') field--error @enderror">
            <label for="first_name">First name <span class="req">required</span></label>
            <input id="first_name" name="first_name" type="text"
                   value="{{ old('first_name', $employee->first_name ?? '') }}" required>
            @error('first_name')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field @error('last_name') field--error @enderror">
            <label for="last_name">Surname <span class="req">required</span></label>
            <input id="last_name" name="last_name" type="text"
                   value="{{ old('last_name', $employee->last_name ?? '') }}" required>
            @error('last_name')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="other_names">Other names</label>
            <input id="other_names" name="other_names" type="text"
                   value="{{ old('other_names', $employee->other_names ?? '') }}">
        </div>

        <div class="field">
            <label for="gender">Sex</label>
            <select id="gender" name="gender">
                <option value="">Not stated</option>
                @foreach (['male' => 'Male', 'female' => 'Female'] as $v => $label)
                    <option value="{{ $v }}" @selected(old('gender', $employee->gender ?? '') === $v)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="field @error('date_of_birth') field--error @enderror">
            <label for="date_of_birth">Date of birth</label>
            <input id="date_of_birth" name="date_of_birth" type="date"
                   value="{{ old('date_of_birth', isset($employee->date_of_birth) ? $employee->date_of_birth->format('Y-m-d') : '') }}">
            @error('date_of_birth')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field @error('national_id') field--error @enderror">
            <label for="national_id">National ID</label>
            <input id="national_id" name="national_id" type="text" maxlength="20" autocapitalize="characters" pattern="\s*([A-Za-z0-9]\s*){14}" title="14 letters and numbers"
                   value="{{ old('national_id', $employee->national_id ?? '') }}">
            <small class="field-hint">NIN: 14 letters and numbers.</small>
            @error('national_id')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field @error('national_id_photo') field--error @enderror">
            <label for="national_id_photo">ID picture <small>(optional)</small></label>
            <input id="national_id_photo" name="national_id_photo" type="file" accept="image/jpeg,image/png,image/webp,application/pdf">
            @if (isset($employee) && $employee->national_id_photo)
                <small class="field-hint"><a href="{{ route('employees.national-id-photo', $employee) }}" target="_blank" rel="noopener">View current ID picture</a> · choosing a new file replaces it.</small>
            @else
                <small class="field-hint">Photo or PDF scan of the ID, up to 5 MB.</small>
            @endif
            @error('national_id_photo')<p class="field-error">{{ $message }}</p>@enderror
        </div>
    </div>
</div>

<div class="card">
    <p class="card-label">How to reach them</p>

    <div class="form-grid">
        <div class="field @error('phone') field--error @enderror">
            <label for="phone">Phone <span class="req">required</span></label>
            <input id="phone" name="phone" type="tel" inputmode="tel"
                   value="{{ old('phone', $employee->phone ?? '') }}" required>
            @error('phone')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="alt_phone">Other phone</label>
            <input id="alt_phone" name="alt_phone" type="tel" inputmode="tel"
                   value="{{ old('alt_phone', $employee->alt_phone ?? '') }}">
        </div>

        <div class="field @error('email') field--error @enderror">
            <label for="email">Email</label>
            <input id="email" name="email" type="email"
                   value="{{ old('email', $employee->email ?? '') }}">
            @error('email')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field field--wide">
            <label for="address">Home address</label>
            <input id="address" name="address" type="text"
                   value="{{ old('address', $employee->address ?? '') }}">
        </div>

        <div class="field">
            <label for="next_of_kin_name">Next of kin</label>
            <input id="next_of_kin_name" name="next_of_kin_name" type="text"
                   value="{{ old('next_of_kin_name', $employee->next_of_kin_name ?? '') }}">
        </div>

        <div class="field">
            <label for="next_of_kin_phone">Next of kin phone</label>
            <input id="next_of_kin_phone" name="next_of_kin_phone" type="tel" inputmode="tel"
                   value="{{ old('next_of_kin_phone', $employee->next_of_kin_phone ?? '') }}">
        </div>
    </div>
</div>

<div class="card">
    <p class="card-label">What they do</p>

    <div class="form-grid">
        <div class="field">
            <label for="department_id">Department</label>
            <select id="department_id" name="department_id">
                <option value="">Not assigned</option>
                @foreach ($departments as $d)
                    <option value="{{ $d->id }}"
                        @selected((int) old('department_id', $employee->department_id ?? 0) === $d->id)>
                        {{ $d->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label for="role_id">Job role</label>
            <select id="role_id" name="role_id">
                <option value="">Not assigned</option>
                @foreach ($roles as $r)
                    <option value="{{ $r->id }}"
                        @selected((int) old('role_id', $employee->role_id ?? 0) === $r->id)>
                        {{ $r->name }}
                    </option>
                @endforeach
            </select>
            <p class="field-hint">This describes the job. It does not grant access — a login is issued separately.</p>
        </div>

        <div class="field">
            <label for="job_title">Job title</label>
            <input id="job_title" name="job_title" type="text"
                   value="{{ old('job_title', $employee->job_title ?? '') }}"
                   placeholder="e.g. Poultry Attendant">
        </div>

        {{--
            Six kinds of worker, each with its own staff number series. A
            three-week builder must never consume a number from the
            permanent range, and a number is never reused when someone
            leaves.
        --}}
        <div class="field">
            <label for="employment_type">Employment type <span class="req">required</span></label>
            <select id="employment_type" name="employment_type" required data-employment>
                @foreach ([
                    'permanent' => 'Permanent',
                    'contract'  => 'Contract',
                    'casual'    => 'Casual worker',
                    'temporary' => 'Temporary — builder, fitter, short job',
                    'intern'    => 'Intern',
                    'student'   => 'Student placement',
                    'vacist'    => 'Holiday worker',
                    'other'     => 'Other worker type',
                ] as $v => $label)
                    <option value="{{ $v }}"
                        @selected(old('employment_type', $employee->employment_type ?? 'permanent') === $v)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <p class="field-hint" data-series-hint></p>
        </div>

        <div class="field">
            <label for="hire_date">Date hired</label>
            <input id="hire_date" name="hire_date" type="date"
                   value="{{ old('hire_date', isset($employee->hire_date) ? $employee->hire_date->format('Y-m-d') : now()->format('Y-m-d')) }}">
        </div>

        {{-- Only asked for people who are here temporarily. --}}
        <div class="field @error('expected_end_date') field--error @enderror" data-temp-only hidden>
            <label for="expected_end_date">Expected to finish</label>
            <input id="expected_end_date" name="expected_end_date" type="date"
                   value="{{ old('expected_end_date', isset($employee->expected_end_date) ? $employee->expected_end_date->format('Y-m-d') : '') }}">
            @error('expected_end_date')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">So HR sees who is finishing rather than finding out on payday.</p>
        </div>

        @if(!isset($employee))
        <div class="field" data-temporary-worker-only hidden>
            <label for="staff_hostel_room_id">Room <small>(optional)</small></label>
            <select id="staff_hostel_room_id" name="staff_hostel_room_id">
                <option value="">No room</option>
                @foreach(($rooms ?? collect()) as $room)
                    <option value="{{ $room->id }}" @selected(old('staff_hostel_room_id') == $room->id)>{{ $room->hostel }} · Room {{ $room->room_number }}</option>
                @endforeach
            </select>
            <p class="field-hint">Choosing a room checks the temporary worker in when you save this form.</p>
        </div>
        @endif

        <div class="field" data-placement-only hidden>
            <label for="placement_from">Sent by</label>
            <input id="placement_from" name="placement_from" type="text"
                   value="{{ old('placement_from', $employee->placement_from ?? '') }}"
                   placeholder="School, college or programme">
        </div>

        <div class="field @error('end_date') field--error @enderror">
            <label for="end_date">Date left</label>
            <input id="end_date" name="end_date" type="date"
                   value="{{ old('end_date', isset($employee->end_date) ? $employee->end_date->format('Y-m-d') : '') }}">
            @error('end_date')<p class="field-error">{{ $message }}</p>@enderror
            <p class="field-hint">Leave blank while they are still employed.</p>
        </div>

        <div class="field">
            <label for="status">Status <span class="req">required</span></label>
            <select id="status" name="status" required>
                @foreach (['active' => 'Active', 'suspended' => 'Suspended', 'terminated' => 'Left'] as $v => $label)
                    <option value="{{ $v }}" @selected(old('status', $employee->status ?? 'active') === $v)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
</div>

<div class="card" @if(!isset($employee)) data-payroll-only @endif>
    <p class="card-label">How they get paid</p>

    <div class="form-grid">
        <div class="field @error('base_salary') field--error @enderror">
            <label for="base_salary">Basic pay (UGX) <span class="req">required</span></label>
            <input id="base_salary" name="base_salary" type="number" inputmode="numeric"
                   min="0" step="1"
                   value="{{ old('base_salary', isset($employee) ? (int) $employee->base_salary : 0) }}" required>
            @error('base_salary')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field">
            <label for="pay_frequency">Paid <span class="req">required</span></label>
            <select id="pay_frequency" name="pay_frequency" required>
                @foreach (['monthly' => 'Monthly', 'weekly' => 'Weekly', 'daily' => 'Daily'] as $v => $label)
                    <option value="{{ $v }}" @selected(old('pay_frequency', $employee->pay_frequency ?? 'monthly') === $v)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <input type="hidden" name="on_payroll" value="0">
            <label class="check-label"><input type="checkbox" name="on_payroll" value="1" @checked(old('on_payroll', isset($employee) ? (int) $employee->on_payroll : 1))> Paid through payroll</label>
            <small class="field-hint">Untick for builders and others paid directly by Finance.</small>
        </div>

        <div class="field">
            <label for="payment_method">Paid by</label>
            <select id="payment_method" name="payment_method" data-pay-method>
                <option value="">Not stated</option>
                @foreach (['cash' => 'Cash', 'bank' => 'Bank transfer', 'mobile_money' => 'Mobile money'] as $v => $label)
                    <option value="{{ $v }}" @selected(old('payment_method', $employee->payment_method ?? '') === $v)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="field @error('mobile_money_number') field--error @enderror" data-pay-block="mobile_money" hidden>
            <label for="mobile_money_number">Mobile money number</label>
            <input id="mobile_money_number" name="mobile_money_number" type="tel" inputmode="tel"
                   value="{{ old('mobile_money_number', $employee->mobile_money_number ?? '') }}">
            @error('mobile_money_number')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field @error('bank_name') field--error @enderror" data-pay-block="bank" hidden>
            <label for="bank_name">Bank</label>
            <input id="bank_name" name="bank_name" type="text"
                   value="{{ old('bank_name', $employee->bank_name ?? '') }}">
            @error('bank_name')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field @error('bank_account') field--error @enderror" data-pay-block="bank" hidden>
            <label for="bank_account">Account number</label>
            <input id="bank_account" name="bank_account" type="text"
                   value="{{ old('bank_account', $employee->bank_account ?? '') }}">
            @error('bank_account')<p class="field-error">{{ $message }}</p>@enderror
        </div>

        <div class="field field--wide">
            <label for="notes">Notes</label>
            <textarea id="notes" name="notes" rows="3">{{ old('notes', $employee->notes ?? '') }}</textarea>
        </div>
    </div>
</div>

<script>
/* Show bank or mobile money fields only when that payment method is chosen. */
(function () {
    var select = document.querySelector('[data-pay-method]');
    if (!select) return;

    function sync() {
        document.querySelectorAll('[data-pay-block]').forEach(function (block) {
            block.hidden = block.dataset.payBlock !== select.value;
        });
    }

    select.addEventListener('change', sync);
    sync();
})();

/*
   Employment type drives three things: which staff number series is used,
   whether an end date is asked for, and whether a placement is relevant.

   The number itself is allocated on save rather than shown live, so the
   hint says which series it will come from — otherwise someone picks
   "Intern" and wonders why the BAF number on screen was not used.
*/
(function () {
    var select = document.querySelector('[data-employment]');
    var hint   = document.querySelector('[data-series-hint]');
    var temp   = document.querySelector('[data-temp-only]');
    var tempAccommodation = document.querySelector('[data-temporary-worker-only]');
    var payrollCard = document.querySelector('[data-payroll-only]');
    var place  = document.querySelector('[data-placement-only]');
    if (!select) return;

    var SERIES = {
        permanent: 'BAF',
        contract:  'BAF',
        casual:    'CAS',
        temporary: 'TMP',
        intern:    'INT',
        student:   'STU',
        vacist:    'VAC',
        other:     'OTH'
    };

    var TEMPORARY = ['contract', 'temporary', 'intern', 'student', 'vacist', 'other'];
    var PLACEMENT = ['intern', 'student', 'vacist'];

    function sync() {
        var v = select.value;

        if (hint) {
            hint.textContent = 'Staff number will be issued in the '
                + (SERIES[v] || 'BAF') + ' series.';
        }

        if (temp)  temp.hidden  = TEMPORARY.indexOf(v) === -1;
        if (tempAccommodation) tempAccommodation.hidden = v !== 'temporary';
        if (payrollCard) {
            payrollCard.hidden = v === 'temporary';
            payrollCard.querySelectorAll('input, select, textarea, button').forEach(function (field) {
                field.disabled = v === 'temporary';
            });
        }
        if (place) place.hidden = PLACEMENT.indexOf(v) === -1;
    }

    select.addEventListener('change', sync);
    sync();
})();
</script>
