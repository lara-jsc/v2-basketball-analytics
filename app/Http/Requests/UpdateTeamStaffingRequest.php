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

                $alreadyMain = (int) $team->main_coach_user_id === $mainCoachUserId;

                if ($mainCoach === null || (int) $mainCoach->team_id !== (int) $team->id) {
                    $validator->errors()->add('main_coach_user_id', 'Select a verified user from this team.');
                } elseif ($mainCoach->email_verified_at === null && ! $alreadyMain) {
                    $validator->errors()->add('main_coach_user_id', 'Select a verified user from this team.');
                }
            }

            if ($assistantCoachUserIds !== []) {
                // Coaches already on staff (e.g. approved before verifying) may be kept as-is.
                $currentAssistantIds = $team->assistantCoaches()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

                $assistants = User::query()
                    ->whereIn('id', $assistantCoachUserIds)
                    ->get()
                    ->keyBy('id');

                foreach ($assistantCoachUserIds as $assistantCoachUserId) {
                    $assistant = $assistants->get($assistantCoachUserId);

                    $unverified = $assistant?->email_verified_at === null
                        && ! in_array($assistantCoachUserId, $currentAssistantIds, true);

                    if ($assistant === null || (int) $assistant->team_id !== (int) $team->id || $unverified) {
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
