<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/*
 | One request class for both adding and editing — the rules are identical,
 | and the code is generated from the name rather than typed.
 */
class BreedRequest extends FormRequest
{
    public function authorize(): bool
    {
        $action = $this->route('breed') ? 'edit' : 'create';

        return $this->user()?->canDo('breeds', $action) ?? false;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:120'],
            'purpose' => ['required', Rule::in(['layer', 'broiler', 'dual'])],

            /*
             | Laying figures. Required for anything that lays, because a
             | breed with no point of lay gives a batch no timetable — which
             | is most of what the module is for.
             |
             | Ordered: lay start < peak < end.
             */
            'lay_start_week' => [
                'nullable', 'required_if:purpose,layer', 'required_if:purpose,dual',
                'integer', 'min:10', 'max:52',
            ],
            'peak_week' => [
                'nullable', 'integer', 'min:10', 'max:80',
                'gte:lay_start_week',
            ],
            'lay_end_week' => [
                'nullable', 'integer', 'min:20', 'max:150',
                'gte:peak_week',
            ],
            'peak_lay_percent' => ['nullable', 'integer', 'min:10', 'max:100'],

            'slaughter_week' => [
                'nullable', 'required_if:purpose,broiler',
                'integer', 'min:3', 'max:30',
            ],

            'mature_weight_g' => ['nullable', 'integer', 'min:200', 'max:10000'],
            'daily_feed_g'    => ['nullable', 'integer', 'min:10', 'max:500'],

            'notes'     => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'lay_start_week.required_if' => 'Give the week laying starts — it is on most delivery notes, and without it the batch gets no timetable.',
            'slaughter_week.required_if' => 'Give the week the birds are ready for market.',
            'peak_week.gte'              => 'Peak cannot come before laying starts.',
            'lay_end_week.gte'           => 'The end of lay cannot come before peak.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
