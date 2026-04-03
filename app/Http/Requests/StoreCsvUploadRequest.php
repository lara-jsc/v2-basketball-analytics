<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCsvUploadRequest extends FormRequest
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
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'file'    => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'The upload must be a CSV file.',
            'file.max'   => 'The CSV file must not exceed 10MB.',
        ];
    }
}
