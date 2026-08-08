<?php

namespace App\Http\Middleware;

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
            $user->loadMissing('team:id,name');
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
                    'team_id' => $user->team_id,
                    'team' => $user->team === null ? null : [
                        'id' => $user->team->id,
                        'name' => $user->team->name,
                    ],
                ],
            ],
            'liveGameInvite' => $liveGameInvite,
        ];
    }
}
