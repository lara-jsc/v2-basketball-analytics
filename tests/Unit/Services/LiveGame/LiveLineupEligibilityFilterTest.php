<?php

namespace Tests\Unit\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
use App\Services\LiveGame\LiveGameEventRules;
use App\Services\LiveGame\LiveLineupEligibilityFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveLineupEligibilityFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_clean_player_is_rankable_with_no_reason(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->stat($game, $player, ['personal_fouls' => 1]);

        $result = $this->filter($game, [$player]);

        $this->assertSame([$player->id], $result->rankablePlayerIds);
        $this->assertSame([], $result->demotedPlayerIds);
        $this->assertSame([], $result->reasons);
    }

    public function test_a_player_at_the_foul_limit_is_excluded_as_disqualified(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->stat($game, $player, ['personal_fouls' => LiveGameEventRules::MAX_PERSONAL_FOULS]);

        $result = $this->filter($game, [$player]);

        $this->assertSame([], $result->rankablePlayerIds);
        $this->assertSame([], $result->demotedPlayerIds);
        $this->assertSame(
            [$player->id => LiveLineupEligibilityFilter::REASON_DISQUALIFIED],
            $result->reasons,
        );
    }

    public function test_a_player_over_the_foul_limit_is_still_excluded_as_disqualified(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->stat($game, $player, ['personal_fouls' => LiveGameEventRules::MAX_PERSONAL_FOULS + 2]);

        $result = $this->filter($game, [$player]);

        $this->assertSame([], $result->rankablePlayerIds);
        $this->assertSame(
            [$player->id => LiveLineupEligibilityFilter::REASON_DISQUALIFIED],
            $result->reasons,
        );
    }

    public function test_a_player_in_foul_trouble_is_demoted_rather_than_ranked(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->stat($game, $player, ['personal_fouls' => LiveLineupEligibilityFilter::FOUL_TROUBLE_THRESHOLD]);

        $result = $this->filter($game, [$player]);

        $this->assertSame([], $result->rankablePlayerIds);
        $this->assertSame([$player->id], $result->demotedPlayerIds);
        $this->assertSame(
            [$player->id => LiveLineupEligibilityFilter::REASON_FOUL_TROUBLE],
            $result->reasons,
        );
    }

    public function test_one_foul_below_the_trouble_threshold_stays_rankable(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->stat($game, $player, ['personal_fouls' => LiveLineupEligibilityFilter::FOUL_TROUBLE_THRESHOLD - 1]);

        $result = $this->filter($game, [$player]);

        $this->assertSame([$player->id], $result->rankablePlayerIds);
        $this->assertSame([], $result->reasons);
    }

    public function test_technical_and_flagrant_fouls_do_not_affect_eligibility(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->stat($game, $player, [
            'personal_fouls' => 1,
            'technical_fouls' => 3,
            'flagrant_fouls' => 2,
        ]);

        $result = $this->filter($game, [$player]);

        $this->assertSame([$player->id], $result->rankablePlayerIds);
        $this->assertSame([], $result->reasons);
    }

    public function test_an_inactive_player_is_excluded_even_with_no_fouls(): void
    {
        [$game, $player] = $this->gameWithPlayer(playerAttributes: ['is_active' => false]);
        $this->stat($game, $player, ['personal_fouls' => 0]);

        $result = $this->filter($game, [$player]);

        $this->assertSame([], $result->rankablePlayerIds);
        $this->assertSame(
            [$player->id => LiveLineupEligibilityFilter::REASON_INACTIVE],
            $result->reasons,
        );
    }

    public function test_disqualification_takes_precedence_over_inactivity(): void
    {
        [$game, $player] = $this->gameWithPlayer(playerAttributes: ['is_active' => false]);
        $this->stat($game, $player, ['personal_fouls' => LiveGameEventRules::MAX_PERSONAL_FOULS]);

        $result = $this->filter($game, [$player]);

        $this->assertSame(
            [$player->id => LiveLineupEligibilityFilter::REASON_DISQUALIFIED],
            $result->reasons,
        );
    }

    public function test_a_player_outside_the_controlled_set_is_ranked_but_locked(): void
    {
        [$game, $mine] = $this->gameWithPlayer();
        $theirs = Player::factory()->for($game->homeTeam)->create();
        $this->stat($game, $mine, ['personal_fouls' => 0]);
        $this->stat($game, $theirs, ['personal_fouls' => 0]);

        $result = $this->filter($game, [$mine, $theirs], controlledPlayerIds: [$mine->id]);

        $this->assertEqualsCanonicalizing([$mine->id, $theirs->id], $result->rankablePlayerIds);
        $this->assertSame([$theirs->id], $result->lockedPlayerIds);
        $this->assertSame(
            [$theirs->id => LiveLineupEligibilityFilter::REASON_ASSIGNED_TO_ASSISTANT],
            $result->reasons,
        );
    }

    public function test_a_locked_player_who_is_disqualified_reports_disqualified_and_is_excluded(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->stat($game, $player, ['personal_fouls' => LiveGameEventRules::MAX_PERSONAL_FOULS]);

        $result = $this->filter($game, [$player], controlledPlayerIds: []);

        $this->assertSame([], $result->rankablePlayerIds);
        $this->assertSame([], $result->lockedPlayerIds);
        $this->assertSame(
            [$player->id => LiveLineupEligibilityFilter::REASON_DISQUALIFIED],
            $result->reasons,
        );
    }

    public function test_a_player_with_no_live_stat_row_yet_is_rankable(): void
    {
        [$game, $player] = $this->gameWithPlayer();

        $result = $this->filter($game, [$player]);

        $this->assertSame([$player->id], $result->rankablePlayerIds);
        $this->assertSame([], $result->reasons);
    }

    public function test_it_partitions_a_mixed_roster_correctly(): void
    {
        [$game, $clean] = $this->gameWithPlayer();
        $trouble = Player::factory()->for($game->homeTeam)->create();
        $fouledOut = Player::factory()->for($game->homeTeam)->create();
        $benched = Player::factory()->for($game->homeTeam)->create(['is_active' => false]);

        $this->stat($game, $clean, ['personal_fouls' => 2]);
        $this->stat($game, $trouble, ['personal_fouls' => LiveLineupEligibilityFilter::FOUL_TROUBLE_THRESHOLD]);
        $this->stat($game, $fouledOut, ['personal_fouls' => LiveGameEventRules::MAX_PERSONAL_FOULS]);
        $this->stat($game, $benched, ['personal_fouls' => 0]);

        $result = $this->filter($game, [$clean, $trouble, $fouledOut, $benched]);

        $this->assertSame([$clean->id], $result->rankablePlayerIds);
        $this->assertSame([$trouble->id], $result->demotedPlayerIds);
        $this->assertSame([
            $trouble->id => LiveLineupEligibilityFilter::REASON_FOUL_TROUBLE,
            $fouledOut->id => LiveLineupEligibilityFilter::REASON_DISQUALIFIED,
            $benched->id => LiveLineupEligibilityFilter::REASON_INACTIVE,
        ], $result->reasons);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $playerAttributes
     * @return array{LiveGame, Player}
     */
    private function gameWithPlayer(array $attributes = [], array $playerAttributes = []): array
    {
        $game = LiveGame::factory()->create($attributes);
        $player = Player::factory()->for($game->homeTeam)->create($playerAttributes);

        return [$game, $player];
    }

    /** @param array<string, mixed> $attributes */
    private function stat(LiveGame $game, Player $player, array $attributes = []): LiveGamePlayerStat
    {
        return LiveGamePlayerStat::query()->create([
            'live_game_id' => $game->id,
            'player_id' => $player->id,
            ...$attributes,
        ]);
    }

    /**
     * @param  list<Player>  $roster
     * @param  list<int>|null  $controlledPlayerIds  null means the coach controls the whole roster
     */
    private function filter(LiveGame $game, array $roster, ?array $controlledPlayerIds = null): object
    {
        $players = Player::query()
            ->whereIn('id', array_map(fn (Player $player): int => $player->id, $roster))
            ->get();

        return app(LiveLineupEligibilityFilter::class)->filter(
            $game,
            $players,
            $controlledPlayerIds ?? $players->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
        );
    }
}
