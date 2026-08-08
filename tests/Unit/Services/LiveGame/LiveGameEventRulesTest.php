<?php

namespace Tests\Unit\Services\LiveGame;

use App\Services\LiveGame\LiveGameEventRules;
use Tests\TestCase;

class LiveGameEventRulesTest extends TestCase
{
    public function test_live_ball_events_require_a_running_clock(): void
    {
        foreach (['shot_made', 'shot_missed', 'rebound', 'assist', 'turnover'] as $type) {
            $this->assertTrue(LiveGameEventRules::isAllowedWhileClockRunning($type), $type);
            $this->assertFalse(LiveGameEventRules::isAllowedWhileClockStopped($type), $type);
        }
    }

    public function test_dead_ball_events_require_a_stopped_clock(): void
    {
        foreach (['free_throw_made', 'free_throw_missed', 'timeout', 'substitution'] as $type) {
            $this->assertFalse(LiveGameEventRules::isAllowedWhileClockRunning($type), $type);
            $this->assertTrue(LiveGameEventRules::isAllowedWhileClockStopped($type), $type);
        }
    }

    public function test_fouls_are_allowed_in_either_clock_state_and_stop_the_clock(): void
    {
        $this->assertTrue(LiveGameEventRules::isAllowedWhileClockRunning('foul'));
        $this->assertTrue(LiveGameEventRules::isAllowedWhileClockStopped('foul'));
        $this->assertTrue(LiveGameEventRules::stopsClock('foul'));
        $this->assertFalse(LiveGameEventRules::stopsClock('shot_made'));
    }

    public function test_only_corrections_survive_the_end_of_a_period(): void
    {
        $this->assertTrue(LiveGameEventRules::isAllowedAtPeriodEnd('correction'));

        foreach (['shot_made', 'free_throw_made', 'timeout', 'substitution', 'foul'] as $type) {
            $this->assertFalse(LiveGameEventRules::isAllowedAtPeriodEnd($type), $type);
        }
    }

    public function test_every_type_the_api_accepts_has_a_clock_requirement(): void
    {
        foreach (LiveGameEventRules::types() as $type) {
            $this->assertContains(LiveGameEventRules::clockRequirement($type), [
                LiveGameEventRules::CLOCK_RUNNING,
                LiveGameEventRules::CLOCK_STOPPED,
                LiveGameEventRules::CLOCK_ANY,
            ], $type);
        }

        $this->assertCount(12, LiveGameEventRules::types());
    }
}
