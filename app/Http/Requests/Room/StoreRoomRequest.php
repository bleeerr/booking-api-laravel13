<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'capacity' => ['required', 'integer', 'min:1'],
            'price_per_hour_minor' => ['required', 'integer', 'min:0'],
            'currency_code' => [
                'required',
                'string',
                'regex:/^[A-Z]{3}$/',
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
