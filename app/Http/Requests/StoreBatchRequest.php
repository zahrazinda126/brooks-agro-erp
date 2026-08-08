<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canDo('batches', 'create') ?? false;
    }

    public function rules(): array
    {
        return [
            'code'          => ['nullable', 'string', 'max:30', Rule::unique('animal_groups', 'code')],
            'name'          => ['nullable', 'string', 'max:120'],
            'department_id' => ['required', 'exists:departments,id'],
            'breed_id'      => ['nullable', 'exists:breeds,id'],
            'purpose'       => ['required', Rule::in(['layer', 'broiler', 'dual', 'kienyeji'])],

            'received_date'       => ['required', 'date', 'before_or_equal:today'],
            'age_days_on_arrival' => ['required', 'integer', 'min:0', 'max:400'],
            'quantity_received'   => ['required', 'integer', 'min:1', 'max:1000000'],

            // Extras thrown in by the supplier, not on the invoice. They
            // count as birds but never as cost.
            'quantity_free'       => ['nullable', 'integer', 'min:0', 'max:100000'],

            'unit_cost'           => ['nullable', 'required_without:total_cost', 'numeric', 'min:0', 'max:9999999'],
            'total_cost'          => ['nullable', 'required_without:unit_cost', 'numeric', 'min:0'],

            'supplier_name' => ['nullable', 'string', 'max:160'],
            'invoice_no'    => ['nullable', 'string', 'max:60'],
            'house'         => ['nullable', 'string', 'max:60'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'received_date.before_or_equal' => 'Birds cannot arrive in the future.',
            'quantity_received.min'         => 'A batch needs at least one bird.',
            'department_id.required'        => 'Choose which section this batch belongs to.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'age_days_on_arrival' => $this->input('age_days_on_arrival') ?: 0,
            'quantity_free'       => $this->input('quantity_free') ?: 0,
            'purpose'             => $this->input('purpose', 'layer'),
        ]);
    }
}
