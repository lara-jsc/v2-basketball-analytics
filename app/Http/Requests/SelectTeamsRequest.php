<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SelectTeamsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_a' => ['required', 'integer', 'exists:teams,id'],
            'team_b' => ['required', 'integer', 'exists:teams,id', 'different:team_a'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_b.different' => 'You must select two different teams.',
        ];
    }
}
