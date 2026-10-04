<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJoinRequestRequest;
use App\Services\TeamJoinRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The signed-in coach's own join request: send one, or withdraw it.
 */
class MyJoinRequestController extends Controller
{
    public function __construct(
        private readonly TeamJoinRequestService $teamJoinRequestService,
    ) {}

    public function store(StoreJoinRequestRequest $request): RedirectResponse
    {
        $this->teamJoinRequestService->submit($request->user(), (int) $request->validated('team_id'));

        return redirect()->route('dashboard')->with('success', 'Request sent to the team\'s main coach.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->teamJoinRequestService->cancel($request->user());

        return redirect()->route('dashboard')->with('success', 'Join request cancelled.');
    }
}
