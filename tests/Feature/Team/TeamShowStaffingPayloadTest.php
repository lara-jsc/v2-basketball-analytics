<?php

namespace Tests\Feature\Team;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TeamShowStaffingPayloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_show_returns_staffing_payload_and_verified_coach_options(): void
    {
        $viewer = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create();
        $mainCoach = User::factory()->forTeam($team)->create([
            'name' => 'Main Coach',
            'email_verified_at' => now(),
        ]);
        $assistantA = User::factory()->forTeam($team)->create([
            'name' => 'Assistant Alpha',
            'email_verified_at' => now(),
        ]);
        $assistantB = User::factory()->forTeam($team)->create([
            'name' => 'Assistant Beta',
            'email_verified_at' => now(),
        ]);
        User::factory()->forTeam($team)->unverified()->create([
            'name' => 'Unverified Coach',
        ]);
        User::factory()->forTeam(Team::factory()->create())->create([
            'name' => 'Foreign Coach',
            'email_verified_at' => now(),
        ]);

        $team->forceFill(['main_coach_user_id' => $mainCoach->id])->save();
        $team->assistantCoaches()->sync([$assistantA->id, $assistantB->id]);

        $this->actingAs($viewer)
            ->get(route('teams.show', $team))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Teams/Show')
                ->where('mainCoach.id', $mainCoach->id)
                ->has('assistantCoaches', 2)
                ->where('assistantCoaches.0.id', $assistantA->id)
                ->where('assistantCoaches.1.id', $assistantB->id)
                ->has('coachOptions', 3)
                ->where('coachOptions.0.id', $assistantA->id)
                ->where('coachOptions.1.id', $assistantB->id)
                ->where('coachOptions.2.id', $mainCoach->id)
            );
    }
}
