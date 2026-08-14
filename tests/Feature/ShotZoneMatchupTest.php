<?php

use App\Models\Player;
use App\Models\PlayerStat;
use App\Models\Team;
use App\Models\User;

it('passes a zone profile for both players', function () {
    $teamA = Team::factory()->create();
    $teamB = Team::factory()->create();
    $user = User::factory()->forTeam($teamA)->create(['email_verified_at' => now()]);

    $playerA = Player::factory()->for($teamA)->create(['is_active' => true]);
    $playerB = Player::factory()->for($teamB)->create(['is_active' => true]);

    // Ensure player_stats rows exist so the comparison page can render.
    PlayerStat::factory()->for($playerA)->create();
    PlayerStat::factory()->for($playerB)->create();

    $this->actingAs($user)
        ->get(route('comparison.show', ['teamA' => $teamA->id, 'teamB' => $teamB->id])
            ."?player_a={$playerA->id}&player_b={$playerB->id}")
        ->assertInertia(fn ($page) => $page
            ->has('playerAShotZones.zones', 5)
            ->has('playerBShotZones.zones', 5)
            ->where('playerAShotZones.total_shots', 0));
});
