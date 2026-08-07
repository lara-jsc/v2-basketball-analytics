<?php

namespace Tests\Unit\Services\LiveGame;

use App\Models\LiveGame;
use App\Models\LiveGameEvent;
use App\Models\LiveGamePlayerStat;
use App\Models\Player;
use App\Services\LiveGame\EligibilityMode;
use App\Services\LiveGame\LiveGameEventRules;
use App\Services\LiveGame\LiveLineupEligibility;
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

    public function test_an_uncontrolled_player_on_court_is_fixed_and_never_a_candidate(): void
    {
        [$game, $mine] = $this->gameWithPlayer();
        $theirs = Player::factory()->for($game->homeTeam)->create();
        $this->stat($game, $mine, ['personal_fouls' => 0]);
        $this->stat($game, $theirs, ['personal_fouls' => 0]);
        $this->putOnCourt($game, [$mine, $theirs]);

        $result = $this->filter($game, [$mine, $theirs], controlledPlayerIds: [$mine->id]);

        $this->assertSame([$mine->id], $result->rankablePlayerIds);
        $this->assertSame([$theirs->id], $result->fixedPlayerIds);
        $this->assertSame(
            [$theirs->id => LiveLineupEligibilityFilter::REASON_ASSIGNED_TO_ASSISTANT],
            $result->reasons,
        );
    }

    public function test_an_uncontrolled_player_on_the_bench_is_excluded_entirely(): void
    {
        [$game, $mine] = $this->gameWithPlayer();
        $theirs = Player::factory()->for($game->homeTeam)->create();
        $this->stat($game, $mine, ['personal_fouls' => 0]);
        $this->stat($game, $theirs, ['personal_fouls' => 0]);
        $this->putOnCourt($game, [$mine]);

        $result = $this->filter($game, [$mine, $theirs], controlledPlayerIds: [$mine->id]);

        $this->assertSame([$mine->id], $result->rankablePlayerIds);
        $this->assertSame([], $result->fixedPlayerIds, 'a bench player holds no place in the five');
        $this->assertSame(
            [$theirs->id => LiveLineupEligibilityFilter::REASON_ASSIGNED_TO_ASSISTANT],
            $result->reasons,
        );
    }

    public function test_an_uncontrolled_player_who_is_disqualified_reports_disqualified_and_is_not_fixed(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->stat($game, $player, ['personal_fouls' => LiveGameEventRules::MAX_PERSONAL_FOULS]);
        $this->putOnCourt($game, [$player]);

        $result = $this->filter($game, [$player], controlledPlayerIds: []);

        $this->assertSame([], $result->rankablePlayerIds);
        $this->assertSame([], $result->fixedPlayerIds, 'a fouled-out player cannot hold a place in the five');
        $this->assertSame(
            [$player->id => LiveLineupEligibilityFilter::REASON_DISQUALIFIED],
            $result->reasons,
        );
    }

    public function test_slot_count_is_five_when_the_coach_controls_the_whole_lineup(): void
    {
        [$game, $first] = $this->gameWithPlayer();
        $others = Player::factory()->count(4)->for($game->homeTeam)->create();
        $roster = [$first, ...$others->all()];
        $this->putOnCourt($game, $roster);

        $result = $this->filter($game, $roster);

        $this->assertSame(5, $result->slotCount());
        $this->assertSame([], $result->fixedPlayerIds);
    }

    public function test_slot_count_shrinks_by_one_for_each_fixed_player_on_court(): void
    {
        [$game, $first] = $this->gameWithPlayer();
        $others = Player::factory()->count(4)->for($game->homeTeam)->create();
        $roster = [$first, ...$others->all()];
        $this->putOnCourt($game, $roster);

        // The assistant holds two of the five on the floor.
        $mine = [$first->id, $others[0]->id, $others[1]->id];

        $result = $this->filter($game, $roster, controlledPlayerIds: $mine);

        $this->assertSame(3, $result->slotCount());
        $this->assertCount(2, $result->fixedPlayerIds);
    }

    public function test_slot_count_is_zero_when_the_assistant_holds_every_player_on_court(): void
    {
        [$game, $first] = $this->gameWithPlayer();
        $others = Player::factory()->count(4)->for($game->homeTeam)->create();
        $roster = [$first, ...$others->all()];
        $this->putOnCourt($game, $roster);

        $result = $this->filter($game, $roster, controlledPlayerIds: []);

        $this->assertSame(0, $result->slotCount());
        $this->assertSame([], $result->rankablePlayerIds);
        $this->assertCount(5, $result->fixedPlayerIds);
    }

    public function test_a_player_with_no_live_stat_row_yet_is_rankable(): void
    {
        [$game, $player] = $this->gameWithPlayer();

        $result = $this->filter($game, [$player]);

        $this->assertSame([$player->id], $result->rankablePlayerIds);
        $this->assertSame([], $result->reasons);
    }

    public function test_three_fouls_in_q1_is_rankable_in_season_mode(): void
    {
        [$game, $player] = $this->gameWithPlayer(['current_period' => 2]);
        $this->stat($game, $player, ['personal_fouls' => 3]);

        $result = $this->filter($game, [$player], mode: EligibilityMode::Season);

        $this->assertSame([$player->id], $result->rankablePlayerIds);
        $this->assertSame([], $result->demotedPlayerIds);
    }

    public function test_three_fouls_in_q1_is_demoted_in_tonight_mode(): void
    {
        [$game, $player] = $this->gameWithPlayer(['current_period' => 2]);
        $this->stat($game, $player, ['personal_fouls' => 3]);

        $result = $this->filter($game, [$player], mode: EligibilityMode::Tonight);

        $this->assertSame([], $result->rankablePlayerIds);
        $this->assertSame([$player->id], $result->demotedPlayerIds);
        $this->assertSame(
            [$player->id => LiveLineupEligibilityFilter::REASON_FOUL_TROUBLE],
            $result->reasons,
        );
    }

    public function test_a_cold_shooter_is_demoted_in_tonight_mode_only(): void
    {
        [$game, $player] = $this->gameWithPlayer();
        $this->stat($game, $player, ['personal_fouls' => 0]);
        foreach ([1, 2, 3] as $sequence) {
            LiveGameEvent::query()->create([
                'live_game_id' => $game->id,
                'sequence' => $sequence,
                'type' => 'shot_missed',
                'team_scope' => 'own',
                'player_id' => $player->id,
                'period' => 1,
                'clock_seconds_remaining' => 600,
                'occurred_at' => now(),
                'payload' => [],
            ]);
        }

        $season = $this->filter($game, [$player], mode: EligibilityMode::Season);
        $tonight = $this->filter($game, [$player], mode: EligibilityMode::Tonight);

        $this->assertSame([$player->id], $season->rankablePlayerIds);
        $this->assertSame([], $season->demotedPlayerIds);
        $this->assertSame([], $tonight->rankablePlayerIds);
        $this->assertSame([$player->id], $tonight->demotedPlayerIds);
        $this->assertSame(
            [$player->id => LiveLineupEligibilityFilter::REASON_COLD_PLAYER],
            $tonight->reasons,
        );
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

    /** @param list<Player> $players */
    private function putOnCourt(LiveGame $game, array $players): void
    {
        $ids = array_map(fn (Player $player): int => $player->id, $players);

        $game->forceFill(['starting_player_ids' => $ids, 'active_player_ids' => $ids])->save();
        $game->refresh();
    }

    /**
     * @param  list<Player>  $roster
     * @param  list<int>|null  $controlledPlayerIds  null means the coach controls the whole roster
     */
    private function filter(
        LiveGame $game,
        array $roster,
        ?array $controlledPlayerIds = null,
        EligibilityMode $mode = EligibilityMode::Season,
    ): LiveLineupEligibility {
        $players = Player::query()
            ->whereIn('id', array_map(fn (Player $player): int => $player->id, $roster))
            ->get();

        return app(LiveLineupEligibilityFilter::class)->filter(
            $game,
            LiveGame::SIDE_HOME,
            $players,
            $controlledPlayerIds ?? $players->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            $mode,
        );
    }
}
