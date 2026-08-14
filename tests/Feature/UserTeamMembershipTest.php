<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\TeamPlayersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTeamMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_belong_to_a_team(): void
    {
        $team = Team::factory()->create();
        $user = User::factory()->forTeam($team)->create();

        $this->assertSame($team->id, $user->fresh()->team_id);
        $this->assertTrue($user->team->is($team));
    }

    public function test_demo_seeder_creates_team_coaches(): void
    {
        $this->seed(TeamPlayersSeeder::class);
        $this->seed(DemoUserSeeder::class);

        $warriors = Team::query()->where('code', 'GSW')->firstOrFail();
        $lakers = Team::query()->where('code', 'LAK')->firstOrFail();

        $this->assertDatabaseHas('users', [
            'email' => 'warriors@email.com',
            'team_id' => $warriors->id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'lakers@email.com',
            'team_id' => $lakers->id,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'test@email.com',
            'team_id' => null,
        ]);
    }
}
