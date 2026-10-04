<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCoachRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $isMain = $this->input('coach_type') === 'main';

        $this->merge([
            'team_mode' => $isMain ? $this->input('team_mode') : null,
            'team_id' => $this->input('team_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'coach_type' => ['required', Rule::in(['main', 'assistant'])],
            'team_mode' => ['nullable', 'required_if:coach_type,main', Rule::in(['create', 'claim'])],
            'team_code' => ['exclude_unless:team_mode,create', 'required', 'string', 'max:10', 'unique:teams,code'],
            'team_name' => ['exclude_unless:team_mode,create', 'required', 'string', 'max:100'],
            'team_id' => [
                Rule::excludeIf(fn () => $this->input('team_mode') === 'create'),
                'required', 'integer', Rule::exists('teams', 'id'),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'coach_type.required' => 'Choose whether you are a main or assistant coach.',
            'team_id.required' => 'Choose a team.',
            'team_code.unique' => 'A team with this code already exists.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('team_mode') === 'create') {
                return;
            }

            $team = Team::query()->find($this->input('team_id'));

            if ($this->input('coach_type') === 'main') {
                if (! $team->is_active) {
                    $validator->errors()->add('team_id', 'This team is inactive.');
                } elseif ($team->main_coach_user_id !== null) {
                    $validator->errors()->add('team_id', 'This team already has a main coach.');
                }

                return;
            }

            if ($team->main_coach_user_id === null) {
                $validator->errors()->add('team_id', 'This team has no main coach to approve your request yet.');
            }
        });
    }
}
