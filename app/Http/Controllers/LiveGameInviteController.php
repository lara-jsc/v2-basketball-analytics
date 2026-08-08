<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LiveGame\LiveGameInviteReader;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LiveGameInviteController extends Controller
{
    public function dismiss(Request $request, string $notification, LiveGameInviteReader $reader): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $reader->dismiss($user, $notification);

        return back();
    }
}
