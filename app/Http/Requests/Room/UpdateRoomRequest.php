<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'nullable', 'string'],
            'capacity' => ['sometimes', 'required', 'integer', 'min:1'],
            'price_per_hour_minor' => [
                'sometimes', 'required', 'integer', 'min:0',
            ],
            'currency_code' => [
                'sometimes', 'required', 'string',
                'regex:/^[A-Z]{3}$/',
            ],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
