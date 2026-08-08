<?php

namespace App\Http\Requests;

use App\Enums\ShotZone;
use App\Models\LiveGameEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttachShotZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var LiveGameEvent $event */
        $event = $this->route('event');

        return $this->user() !== null
            && (int) $event->recorded_by_user_id === (int) $this->user()->id;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'zone' => ['required', 'string', Rule::in(ShotZone::values())],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            /** @var LiveGameEvent $event */
            $event = $this->route('event');
            $zone = ShotZone::tryFrom((string) $this->input('zone'));

            if ((int) $event->live_game_id !== (int) $this->route('liveGame')->id) {
                $validator->errors()->add('zone', 'That event does not belong to this live game.');

                return;
            }

            if (! in_array($event->type, ['shot_made', 'shot_missed'], true)) {
                $validator->errors()->add('zone', 'Only field goal attempts carry a location.');

                return;
            }

            if (($event->payload['zone'] ?? null) !== null) {
                $validator->errors()->add('zone', 'This shot already has a location.');
            }

            if ($zone !== null && $zone->points() !== (int) ($event->payload['points'] ?? 0)) {
                $validator->errors()->add('zone', 'That location does not match the value of this shot.');
            }

            $isVoided = $this->route('liveGame')->events()
                ->where('type', 'correction')
                ->where('voids_event_id', $event->id)
                ->exists();

            if ($isVoided) {
                $validator->errors()->add('zone', 'This shot has been voided.');
            }
        });
    }
}
