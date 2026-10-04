<?php

use App\Enums\JoinRequestStatus;
use App\Models\Team;
use App\Models\TeamJoinRequest;
use App\Models\User;
use Illuminate\Database\QueryException;

it('stores a pending join request linked to team and user', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    $request = TeamJoinRequest::query()->create([
        'team_id' => $team->id,
        'user_id' => $user->id,
        'status' => JoinRequestStatus::Pending,
    ]);

    expect($request->fresh()->status)->toBe(JoinRequestStatus::Pending)
        ->and($user->joinRequest->id)->toBe($request->id)
        ->and($team->joinRequests()->count())->toBe(1);
});

it('allows only one join request per user', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $attrs = ['team_id' => $team->id, 'user_id' => $user->id, 'status' => JoinRequestStatus::Pending];

    TeamJoinRequest::query()->create($attrs);
    TeamJoinRequest::query()->create($attrs);
})->throws(QueryException::class);
