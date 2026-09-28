<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAccountRequest extends FormRequest
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
            'password' => $this->input('password') ?: null,
        ]);
    }

    public function rules(): array
    {
        /** @var User $account */
        $account = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($account->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::enum(UserRole::class)],
            'team_id' => ['nullable', 'required_if:role,'.UserRole::Coach->value, 'integer', Rule::exists('teams', 'id')],
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
            /** @var User $account */
            $account = $this->route('user');

            $demotingAdmin = $account->isAdmin() && $this->input('role') !== UserRole::Admin->value;

            if ($demotingAdmin && User::query()->where('role', UserRole::Admin)->count() <= 1) {
                $validator->errors()->add('role', 'At least one admin account must remain.');
            }
        });
    }
}
