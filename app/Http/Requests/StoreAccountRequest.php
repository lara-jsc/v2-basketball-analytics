<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isAdmin = $this->input('role') === UserRole::Admin->value;

        $this->merge([
            'team_id' => $isAdmin ? null : ($this->input('team_id') ?: null),
            'staffing' => $isAdmin ? 'none' : ($this->input('staffing') ?: 'none'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'team_id' => ['nullable', 'required_if:role,'.UserRole::Coach->value, 'integer', Rule::exists('teams', 'id')],
            'staffing' => ['required', Rule::in(['none', 'main', 'assistant'])],
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required_if' => 'Choose the team this coach belongs to.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('staffing') !== 'main') {
                return;
            }

            $team = Team::query()->find($this->input('team_id'));

            if ($team?->main_coach_user_id !== null) {
                $validator->errors()->add('staffing', 'This team already has a main coach.');
            }
        });
    }
}
