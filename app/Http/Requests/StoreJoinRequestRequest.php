<?php

namespace App\Http\Requests;

use App\Enums\JoinRequestStatus;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJoinRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')],
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.required' => 'Choose a team.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var User $user */
            $user = $this->user();

            if ($user->isAdmin()) {
                $validator->errors()->add('team_id', 'Admins are not tied to a team.');

                return;
            }

            if ($user->team_id !== null) {
                $validator->errors()->add('team_id', 'You already belong to a team.');

                return;
            }

            if ($user->joinRequest()->where('status', JoinRequestStatus::Pending)->exists()) {
                $validator->errors()->add('team_id', 'Cancel your current request first.');

                return;
            }

            if (Team::query()->find($this->input('team_id'))?->main_coach_user_id === null) {
                $validator->errors()->add('team_id', 'This team has no main coach to approve your request yet.');
            }
        });
    }
}
