<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Services\TeamJoinRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamJoinRequestController extends Controller
{
    public function __construct(
        private readonly TeamJoinRequestService $teamJoinRequestService,
    ) {}

    public function approve(Request $request, Team $team, TeamJoinRequest $joinRequest): RedirectResponse
    {
        $this->teamJoinRequestService->approve($joinRequest, $request->user());

        return redirect()
            ->route('teams.show', $team)
            ->with('success', "{$joinRequest->user->name} joined as an assistant coach.");
    }

    public function reject(Request $request, Team $team, TeamJoinRequest $joinRequest): RedirectResponse
    {
        $this->teamJoinRequestService->reject($joinRequest, $request->user());

        return redirect()
            ->route('teams.show', $team)
            ->with('success', 'Join request declined.');
    }
}
