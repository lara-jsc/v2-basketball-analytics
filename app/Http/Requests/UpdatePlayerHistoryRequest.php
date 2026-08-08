<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlayerHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('is_started')) {
            $this->merge([
                'is_started' => filter_var($this->is_started, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            ]);
        }
    }

    /**
     * All fields are optional on update — only validated when present.
     * playing_team_id/opponent_team_id are re-validated together when either changes.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opponent_team_id' => ['sometimes', 'integer', 'exists:teams,id'],
            'game_date' => ['sometimes', 'date_format:Y-m-d'],
            'position_played' => ['sometimes', 'nullable', 'string', 'max:50'],
            'minutes_played' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999.99'],
            'points' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'field_goals_made' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'field_goals_attempted' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'three_pointers_made' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'three_pointers_attempted' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'free_throws_made' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'free_throws_attempted' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'offensive_rebounds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'defensive_rebounds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'rebounds' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'assists' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'steals' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'blocks' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'turnovers' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'personal_fouls' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'flagrant_fouls' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'technical_fouls' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'ejections' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'disqualifications' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:255'],
            'is_started' => ['sometimes', 'boolean'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [];
    }
}
