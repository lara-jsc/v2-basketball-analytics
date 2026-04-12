<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\PlayerHistory;
use App\Models\PlayerStat;
use Illuminate\Http\JsonResponse;

class PlayerStatController extends Controller
{
    /**
     * Return a breakdown of how a player's plus/minus aggregate was computed.
     *
     * Returns 404 if the player has no PlayerStat record yet.
     */
    public function plusMinusBreakdown(Player $player): JsonResponse
    {
        /** @var PlayerStat|null $stat */
        $stat = PlayerStat::where('player_id', $player->id)->first();

        if ($stat === null) {
            return response()->json(['message' => 'No stats found for this player.'], 404);
        }

        $gamesTotal    = PlayerHistory::where('player_id', $player->id)->count();
        $gamesWithData = PlayerHistory::where('player_id', $player->id)
            ->whereNotNull('plus_minus')
            ->count();
        $gamesExcluded = $gamesTotal - $gamesWithData;
        $computedValue = $stat->plus_minus;

        if ($computedValue === null) {
            return response()->json([
                'player_id'        => $player->id,
                'player_name'      => "{$player->first_name} {$player->last_name}",
                'formula'          => 'SUM(plus_minus) across all game records',
                'formula_notation' => 'Σ +/- per game',
                'games_with_data'  => $gamesWithData,
                'games_total'      => $gamesTotal,
                'computed_value'   => null,
                'display_value'    => null,
                'note'             => 'Plus/minus has not been computed yet for this player.',
            ]);
        }

        $displayValue = $computedValue >= 0
            ? "+{$computedValue}"
            : (string) $computedValue;

        $note = $gamesExcluded > 0
            ? "{$gamesExcluded} game record(s) have no +/- data (recorded as null) and are excluded from the sum."
            : 'All game records have +/- data.';

        return response()->json([
            'player_id'        => $player->id,
            'player_name'      => "{$player->first_name} {$player->last_name}",
            'formula'          => 'SUM(plus_minus) across all game records',
            'formula_notation' => 'Σ +/- per game',
            'games_with_data'  => $gamesWithData,
            'games_total'      => $gamesTotal,
            'computed_value'   => $computedValue,
            'display_value'    => $displayValue,
            'note'             => $note,
        ]);
    }
}
