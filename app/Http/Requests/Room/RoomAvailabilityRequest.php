<?php

namespace App\Http\Requests\Room;

use Illuminate\Foundation\Http\FormRequest;

class RoomAvailabilityRequest extends FormRequest
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
        $rfc3339 = 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00))$/';

        return [
            'starts_at' => [
                'required',
                'date',
                $rfc3339,
            ],
            'ends_at' => [
                'required',
                'date',
                $rfc3339,
                'after:starts_at',
            ],
        ];
    }
}
