<?php

namespace App\Http\Requests;

use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamStaffingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $assistantCoachUserIds = $this->input('assistant_coach_user_ids', []);

        $this->merge([
            'main_coach_user_id' => $this->input('main_coach_user_id') ?: null,
            'assistant_coach_user_ids' => is_array($assistantCoachUserIds) ? $assistantCoachUserIds : [],
        ]);
    }

    public function rules(): array
    {
        return [
            'main_coach_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'assistant_coach_user_ids' => ['array'],
            'assistant_coach_user_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Team $team */
            $team = $this->route('team');

            $mainCoachUserId = $this->validatedMainCoachUserId();
            $assistantCoachUserIds = $this->validatedAssistantCoachUserIds();

            if ($mainCoachUserId !== null) {
                $mainCoach = User::query()->find($mainCoachUserId);

                if ($mainCoach === null || (int) $mainCoach->team_id !== (int) $team->id) {
                    $validator->errors()->add('main_coach_user_id', 'Select a verified user from this team.');
                } elseif ($mainCoach->email_verified_at === null) {
                    $validator->errors()->add('main_coach_user_id', 'Select a verified user from this team.');
                }
            }

            if ($assistantCoachUserIds !== []) {
                $assistants = User::query()
                    ->whereIn('id', $assistantCoachUserIds)
                    ->get()
                    ->keyBy('id');

                foreach ($assistantCoachUserIds as $assistantCoachUserId) {
                    $assistant = $assistants->get($assistantCoachUserId);

                    if ($assistant === null || (int) $assistant->team_id !== (int) $team->id || $assistant->email_verified_at === null) {
                        $validator->errors()->add('assistant_coach_user_ids', 'Select only verified users from this team.');
                        break;
                    }
                }
            }

            if ($mainCoachUserId !== null && in_array($mainCoachUserId, $assistantCoachUserIds, true)) {
                $validator->errors()->add('main_coach_user_id', 'The main coach cannot also be an assistant coach.');
                $validator->errors()->add('assistant_coach_user_ids', 'The main coach cannot also be an assistant coach.');
            }
        });
    }

    public function staffingPayload(): array
    {
        return [
            'main_coach_user_id' => $this->validatedMainCoachUserId(),
            'assistant_coach_user_ids' => $this->validatedAssistantCoachUserIds(),
        ];
    }

    private function validatedMainCoachUserId(): ?int
    {
        $value = $this->input('main_coach_user_id');

        return $value === null || $value === '' ? null : (int) $value;
    }

    /** @return list<int> */
    private function validatedAssistantCoachUserIds(): array
    {
        $value = $this->input('assistant_coach_user_ids', []);

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $id): int => (int) $id, $value));
    }
}
