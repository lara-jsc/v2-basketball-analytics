<?php

namespace Tests\Feature\Team;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TeamCoachStaffingUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_a_main_coach_assignment(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $mainCoach = User::factory()->forTeam($team)->create();

        $this->actingAs($actor)
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => $mainCoach->id,
                'assistant_coach_user_ids' => [],
            ])
            ->assertRedirect(route('teams.show', $team));

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'main_coach_user_id' => $mainCoach->id,
        ]);
    }

    public function test_it_saves_multiple_assistant_coaches(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $assistantA = User::factory()->forTeam($team)->create();
        $assistantB = User::factory()->forTeam($team)->create();

        $this->actingAs($actor)
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => null,
                'assistant_coach_user_ids' => [$assistantA->id, $assistantB->id],
            ])
            ->assertRedirect(route('teams.show', $team));

        $this->assertDatabaseHas('team_assistant_coaches', [
            'team_id' => $team->id,
            'user_id' => $assistantA->id,
        ]);
        $this->assertDatabaseHas('team_assistant_coaches', [
            'team_id' => $team->id,
            'user_id' => $assistantB->id,
        ]);
    }

    public function test_it_replaces_previous_assistant_assignments(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $oldAssistant = User::factory()->forTeam($team)->create();
        $newAssistant = User::factory()->forTeam($team)->create();

        $team->assistantCoaches()->attach($oldAssistant->id);

        $this->actingAs($actor)
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => null,
                'assistant_coach_user_ids' => [$newAssistant->id],
            ])
            ->assertRedirect(route('teams.show', $team));

        $this->assertDatabaseMissing('team_assistant_coaches', [
            'team_id' => $team->id,
            'user_id' => $oldAssistant->id,
        ]);
        $this->assertDatabaseHas('team_assistant_coaches', [
            'team_id' => $team->id,
            'user_id' => $newAssistant->id,
        ]);
    }

    public function test_it_rejects_a_main_coach_from_another_team(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $foreignCoach = User::factory()->forTeam(Team::factory()->create())->create();

        $this->actingAs($actor)
            ->from(route('teams.show', $team))
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => $foreignCoach->id,
                'assistant_coach_user_ids' => [],
            ])
            ->assertRedirect(route('teams.show', $team))
            ->assertSessionHasErrors('main_coach_user_id');
    }

    public function test_it_rejects_assistant_coaches_from_another_team(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $foreignAssistant = User::factory()->forTeam(Team::factory()->create())->create();

        $this->actingAs($actor)
            ->from(route('teams.show', $team))
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => null,
                'assistant_coach_user_ids' => [$foreignAssistant->id],
            ])
            ->assertRedirect(route('teams.show', $team))
            ->assertSessionHasErrors('assistant_coach_user_ids');
    }

    public function test_it_rejects_unverified_users(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $unverifiedMain = User::factory()->forTeam($team)->unverified()->create();
        $unverifiedAssistant = User::factory()->forTeam($team)->unverified()->create();

        $this->actingAs($actor)
            ->from(route('teams.show', $team))
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => $unverifiedMain->id,
                'assistant_coach_user_ids' => [$unverifiedAssistant->id],
            ])
            ->assertRedirect(route('teams.show', $team))
            ->assertSessionHasErrors(['main_coach_user_id', 'assistant_coach_user_ids']);
    }

    public function test_it_rejects_duplicate_assistant_ids(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $assistant = User::factory()->forTeam($team)->create();

        $this->actingAs($actor)
            ->from(route('teams.show', $team))
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => null,
                'assistant_coach_user_ids' => [$assistant->id, $assistant->id],
            ])
            ->assertRedirect(route('teams.show', $team))
            ->assertSessionHasErrors('assistant_coach_user_ids.1');
    }

    public function test_it_rejects_overlap_between_main_and_assistant_assignments(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $coach = User::factory()->forTeam($team)->create();

        $this->actingAs($actor)
            ->from(route('teams.show', $team))
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => $coach->id,
                'assistant_coach_user_ids' => [$coach->id],
            ])
            ->assertRedirect(route('teams.show', $team))
            ->assertSessionHasErrors(['main_coach_user_id', 'assistant_coach_user_ids']);
    }

    public function test_it_accepts_clearing_all_assignments(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $mainCoach = User::factory()->forTeam($team)->create();
        $assistant = User::factory()->forTeam($team)->create();

        $team->forceFill(['main_coach_user_id' => $mainCoach->id])->save();
        $team->assistantCoaches()->attach($assistant->id);

        $this->actingAs($actor)
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => null,
                'assistant_coach_user_ids' => [],
            ])
            ->assertRedirect(route('teams.show', $team));

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'main_coach_user_id' => null,
        ]);
        $this->assertDatabaseCount('team_assistant_coaches', 0);
    }

    public function test_the_follow_up_team_show_payload_contains_the_full_updated_staffing_summary(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
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

        $this->actingAs($actor)
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => $mainCoach->id,
                'assistant_coach_user_ids' => [$assistantA->id, $assistantB->id],
            ])
            ->assertRedirect(route('teams.show', $team));

        $this->actingAs($actor)
            ->get(route('teams.show', $team))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Teams/Show')
                ->where('mainCoach.id', $mainCoach->id)
                ->has('assistantCoaches', 2)
                ->where('assistantCoaches.0.id', $assistantA->id)
                ->where('assistantCoaches.1.id', $assistantB->id)
            );
    }

    /** @return array{0: User, 1: Team} */
    public function test_it_keeps_an_unverified_coach_who_already_holds_the_main_slot(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $selfSignup = User::factory()->unverified()->forTeam($team)->create();
        $team->forceFill(['main_coach_user_id' => $selfSignup->id])->save();
        $assistant = User::factory()->forTeam($team)->create();

        $this->actingAs($actor)
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => $selfSignup->id,
                'assistant_coach_user_ids' => [$assistant->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('teams.show', $team));

        $this->assertSame([$assistant->id], $team->assistantCoaches()->pluck('users.id')->all());
    }

    public function test_it_still_rejects_promoting_an_unverified_coach_into_the_main_slot(): void
    {
        [$actor, $team] = $this->actingUserAndTeam();
        $unverified = User::factory()->unverified()->forTeam($team)->create();

        $this->actingAs($actor)
            ->put(route('teams.staffing.update', $team), [
                'main_coach_user_id' => $unverified->id,
                'assistant_coach_user_ids' => [],
            ])
            ->assertSessionHasErrors('main_coach_user_id');
    }

    private function actingUserAndTeam(): array
    {
        $team = Team::factory()->create();
        $actor = User::factory()->admin()->create();

        return [$actor, $team];
    }
}
