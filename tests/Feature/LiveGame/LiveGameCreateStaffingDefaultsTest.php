<?php

namespace Tests\Feature\LiveGame;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LiveGameCreateStaffingDefaultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_prefills_the_single_staffed_assistant_coach(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);
        $assistant = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);

        $team->assistantCoaches()->sync([$assistant->id]);

        $this->actingAs($user)
            ->get(route('live-games.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('LiveGames/Create')
                ->where('defaultAssistantCoachUserId', $assistant->id)
            );
    }

    public function test_create_page_leaves_the_default_unset_when_multiple_staffed_assistants_exist(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);
        $assistantA = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);
        $assistantB = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);

        $team->assistantCoaches()->sync([$assistantA->id, $assistantB->id]);

        $this->actingAs($user)
            ->get(route('live-games.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('LiveGames/Create')
                ->where('defaultAssistantCoachUserId', null)
                ->has('assistantCoachOptions', 2)
            );
    }

    public function test_create_page_leaves_the_default_unset_when_no_staffed_assistants_exist(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->forTeam($team)->create(['email_verified_at' => now()]);
        User::factory()->forTeam($team)->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get(route('live-games.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('LiveGames/Create')
                ->where('defaultAssistantCoachUserId', null)
            );
    }
}
