<?php

namespace App\Http\Middleware;

use App\Repositories\TeamJoinRequestRepository;
use App\Services\LiveGame\LiveGameInviteReader;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        if ($user !== null) {
            $user->loadMissing(['team:id,name', 'joinRequest.team:id,name']);
        }

        $liveGameInvite = null;
        if ($user !== null) {
            $liveGameInvite = app(LiveGameInviteReader::class)->activeBannerFor($user);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'is_admin' => $user->isAdmin(),
                    'team_id' => $user->team_id,
                    'team' => $user->team === null ? null : [
                        'id' => $user->team->id,
                        'name' => $user->team->name,
                    ],
                    // A request only matters while the user has no team yet.
                    'join_request' => $user->joinRequest === null || $user->team_id !== null ? null : [
                        'status' => $user->joinRequest->status->value,
                        'team_name' => $user->joinRequest->team->name,
                        'requested_at' => $user->joinRequest->updated_at?->toIso8601String(),
                    ],
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'liveGameInvite' => $liveGameInvite,
            // Pending join requests this user can decide on, per team.
            'joinRequestQueue' => fn () => $user === null
                ? []
                : app(TeamJoinRequestRepository::class)->queueFor($user),
        ];
    }
}
