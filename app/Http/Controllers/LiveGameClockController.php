<?php

namespace App\Http\Controllers;

use App\Models\LiveGame;
use App\Models\User;
use App\Services\LiveGame\LiveGameClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LiveGameClockController extends Controller
{
    public function store(Request $request, LiveGame $liveGame, LiveGameClockService $clock): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($clock->handle($liveGame, $user, $this->validated($request, $liveGame)));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, LiveGame $game): array
    {
        return Validator::make($request->all(), [
            'action' => ['required', 'string', Rule::in(['start', 'stop', 'set_period', 'reset_period'])],
            'period' => ['required_if:action,set_period', 'nullable', 'integer', 'between:1,4'],
            'clock_seconds_remaining' => ['nullable', 'integer', 'min:0', "max:{$game->period_length_seconds}"],
        ])->validate();
    }
}
