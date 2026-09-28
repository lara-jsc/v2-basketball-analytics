<?php

use App\Models\PlayerStat;

// SQLite ignores decimal precision, so this only catches truncation when the suite runs on MySQL.
it('keeps four decimals on shooting fractions', function () {
    $values = [
        'fg_pct' => 0.4615, 'ft_pct' => 0.8333, 'three_p_pct' => 0.3571,
        'sh_eff' => -0.4277, 'efg_pct' => 0.5385, 'ts_pct' => 0.5403,
    ];

    $stat = PlayerStat::factory()->create($values);

    expect($stat->fresh()->only(array_keys($values)))->toBe($values);
});
