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
            'breed_name'    => ['nullable', 'string', 'max:120'],
            'purpose'       => ['required', Rule::in(['layer', 'broiler', 'dual', 'kienyeji'])],
            'batch_order_id'=>['nullable','exists:batch_orders,id'],

            'received_date'       => ['required', 'date', 'before_or_equal:today'],
            'age_days_on_arrival' => ['required', 'integer', 'min:0', 'max:400'],
            'quantity_received'   => ['required', 'integer', 'min:1', 'max:1000000'],

            // Extras thrown in by the supplier, not on the invoice. They
            // count as birds but never as cost.
            'quantity_free'       => ['nullable', 'integer', 'min:0', 'max:100000'],
            'transit_deaths'      => ['nullable', 'integer', 'min:0', 'max:1000000'],

            'unit_cost'           => [$this->user()->isProductionUser() ? 'exclude' : 'nullable', 'required_without:total_cost', 'numeric', 'min:0', 'max:9999999'],
            'total_cost'          => [$this->user()->isProductionUser() ? 'exclude' : 'nullable', 'required_without:unit_cost', 'numeric', 'min:0'],

            'supplier_name' => [$this->user()->isProductionUser() ? 'exclude' : 'nullable', 'string', 'max:160'],
            'invoice_no'    => [$this->user()->isProductionUser() ? 'exclude' : 'nullable', 'string', 'max:60'],
            'house'         => ['nullable', 'string', 'max:60'],
            'house_allocations' => ['nullable', 'array'],
            'house_allocations.*' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'new_houses' => ['nullable', 'array'],
            'new_houses.*.house_number' => ['nullable', 'string', 'max:60', 'distinct', 'unique:poultry_houses,house_number'],
            'new_houses.*.house_type' => ['nullable', Rule::in(['brooder', 'standard'])],
            'new_houses.*.bird_quantity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'notes'         => [$this->user()->isProductionUser() ? 'exclude' : 'nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $delivered = (int) $this->input('quantity_received') + (int) $this->input('quantity_free');
            $transitDeaths = (int) $this->input('transit_deaths');
            $expected = $delivered - $transitDeaths;
            if ($transitDeaths > $delivered) {
                $validator->errors()->add('transit_deaths', 'Transit deaths cannot exceed all paid and free birds supplied.');
                return;
            }
            $allocated = collect($this->input('house_allocations', []))->sum(fn ($quantity) => (int) $quantity)
                + collect($this->input('new_houses', []))->sum(fn ($house) => (int) ($house['bird_quantity'] ?? 0));

            foreach ($this->input('new_houses', []) as $index => $house) {
                $hasNumber = trim((string) ($house['house_number'] ?? '')) !== '';
                $hasBirds = (int) ($house['bird_quantity'] ?? 0) > 0;
                if ($hasNumber xor $hasBirds) {
                    $validator->errors()->add("new_houses.$index.house_number", 'Enter both the house number and its number of birds.');
                }
            }

            if ($allocated !== $expected) {
                $validator->errors()->add('house_allocations',
                    'Allocate all '.number_format($expected).' birds across the houses. The current allocation is '.number_format($allocated).'.');
            }

            if ((int) $this->input('age_days_on_arrival') <= config('farm.brooder_max_age_days') && $allocated > 0) {
                $selectedIds = collect($this->input('house_allocations', []))->filter(fn ($quantity) => (int) $quantity > 0)->keys();
                $hasStandardHouse = \App\Models\PoultryHouse::whereIn('id', $selectedIds)->where('house_type', '!=', 'brooder')->exists();
                $hasStandardNewHouse = collect($this->input('new_houses', []))->contains(fn ($house) =>
                    (int) ($house['bird_quantity'] ?? 0) > 0 && ($house['house_type'] ?? 'standard') !== 'brooder');
                if ($hasStandardHouse || $hasStandardNewHouse) {
                    $validator->errors()->add('house_allocations', 'Birds aged '.config('farm.brooder_max_age_days').' days or less must first be allocated to houses marked as brooders.');
                }
            }
        });
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
            'transit_deaths'      => $this->input('transit_deaths') ?: 0,
            'purpose'             => $this->input('purpose', 'layer'),
        ]);
    }
}
