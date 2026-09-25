<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canDo('hr', 'create') ?? false;
    }

    public function rules(): array
    {
        return [
            'staff_no'    => ['nullable', 'string', 'max:30', 'regex:/^BAF [0-9]{3,}$/', Rule::unique('employees', 'staff_no')],
            'first_name'  => ['required', 'string', 'max:80'],
            'last_name'   => ['required', 'string', 'max:80'],
            'other_names' => ['nullable', 'string', 'max:80'],

            'phone'         => ['required', 'string', 'max:30'],
            'alt_phone'     => ['nullable', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:150'],
            'national_id'   => ['nullable', 'string', 'max:40', Rule::unique('employees', 'national_id')],
            'gender'        => ['nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'address'       => ['nullable', 'string', 'max:500'],

            'next_of_kin_name'  => ['nullable', 'string', 'max:120'],
            'next_of_kin_phone' => ['nullable', 'string', 'max:30'],

            'department_id' => ['nullable', 'exists:departments,id'],
            'role_id'       => ['nullable', 'exists:roles,id'],
            'job_title'     => ['nullable', 'string', 'max:120'],

            /*
             | Six kinds of worker, each with its own staff number series.
             | A three-week builder must never consume a number from the
             | permanent range.
             */
            'employment_type' => ['required', Rule::in([
                'permanent', 'contract', 'casual', 'temporary', 'intern', 'student', 'vacist', 'other',
            ])],

            'hire_date' => ['nullable', 'date'],

            // Anyone temporary should have an expected leaving date, so HR
            // sees who is finishing rather than finding out on payday.
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'placement_from'    => ['nullable', 'string', 'max:160'],

            'end_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'status'   => ['required', Rule::in(['active', 'suspended', 'terminated'])],

            'base_salary'    => ['required', 'numeric', 'min:0', 'max:9999999999'],
            'pay_frequency'  => ['required', Rule::in(['monthly', 'weekly', 'daily'])],
            'payment_method' => ['nullable', Rule::in(['cash', 'bank', 'mobile_money'])],

            'bank_name'           => ['nullable', 'required_if:payment_method,bank', 'string', 'max:120'],
            'bank_account'        => ['nullable', 'required_if:payment_method,bank', 'string', 'max:60'],
            'mobile_money_number' => ['nullable', 'required_if:payment_method,mobile_money', 'string', 'max:30'],

            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'bank_name.required_if'           => 'Give the bank name, since pay goes to a bank account.',
            'bank_account.required_if'        => 'Give the account number, since pay goes to a bank account.',
            'mobile_money_number.required_if' => 'Give the mobile money number, since pay goes by mobile money.',
            'national_id.unique'              => 'Another employee is already registered with that national ID.',
            'end_date.after_or_equal'         => 'The end date cannot be before the hire date.',
            'expected_end_date.after_or_equal' => 'The expected end date cannot be before the hire date.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'status'          => $this->input('status', 'active'),
            'employment_type' => $this->input('employment_type', 'permanent'),
            'pay_frequency'   => $this->input('pay_frequency', 'monthly'),
            'base_salary'     => $this->input('base_salary') ?: 0,
        ]);
    }
}
