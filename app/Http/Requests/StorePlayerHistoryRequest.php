<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePlayerHistoryRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'playing_team_id'          => ['required', 'integer', 'exists:teams,id'],
            'opponent_team_id'         => ['required', 'integer', 'exists:teams,id', 'different:playing_team_id'],
            'game_date'                => ['required', 'date_format:Y-m-d'],
            'position_played'          => ['nullable', 'string', 'max:50'],
            'minutes_played'           => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'points'                   => ['nullable', 'integer', 'min:0', 'max:255'],
            'field_goals_made'         => ['nullable', 'integer', 'min:0', 'max:255'],
            'field_goals_attempted'    => ['nullable', 'integer', 'min:0', 'max:255'],
            'three_pointers_made'      => ['nullable', 'integer', 'min:0', 'max:255'],
            'three_pointers_attempted' => ['nullable', 'integer', 'min:0', 'max:255'],
            'free_throws_made'         => ['nullable', 'integer', 'min:0', 'max:255'],
            'free_throws_attempted'    => ['nullable', 'integer', 'min:0', 'max:255'],
            'offensive_rebounds'       => ['nullable', 'integer', 'min:0', 'max:255'],
            'defensive_rebounds'       => ['nullable', 'integer', 'min:0', 'max:255'],
            'rebounds'                 => ['nullable', 'integer', 'min:0', 'max:255'],
            'assists'                  => ['nullable', 'integer', 'min:0', 'max:255'],
            'steals'                   => ['nullable', 'integer', 'min:0', 'max:255'],
            'blocks'                   => ['nullable', 'integer', 'min:0', 'max:255'],
            'turnovers'                => ['nullable', 'integer', 'min:0', 'max:255'],
            'personal_fouls'           => ['nullable', 'integer', 'min:0', 'max:255'],
            'flagrant_fouls'           => ['nullable', 'integer', 'min:0', 'max:255'],
            'technical_fouls'          => ['nullable', 'integer', 'min:0', 'max:255'],
            'ejections'                => ['nullable', 'integer', 'min:0', 'max:255'],
            'disqualifications'        => ['nullable', 'integer', 'min:0', 'max:255'],
            'is_started'               => ['boolean'],
            'notes'                    => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'opponent_team_id.different' => 'The opponent team must be different from the playing team.',
        ];
    }
}
