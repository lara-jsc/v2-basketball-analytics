<?php

namespace App\Actions;

use App\Models\PlayerHistory;
use App\Repositories\PlayerHistoryRepository;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Validates and upserts a single player history row.
 *
 * Used by PlayerHistoryImportJob for per-row processing.
 * Throws RuntimeException with a human-readable message on any validation failure
 * so the job can log it and continue to the next row.
 */
class UpsertPlayerHistoryAction
{
    /** Non-negative integer stat columns from the CSV */
    private const INT_STAT_COLUMNS = [
        'points', 'field_goals_made', 'field_goals_attempted',
        'three_pointers_made', 'three_pointers_attempted',
        'free_throws_made', 'free_throws_attempted',
        'offensive_rebounds', 'defensive_rebounds', 'rebounds',
        'assists', 'steals', 'blocks', 'turnovers',
        'personal_fouls', 'flagrant_fouls', 'technical_fouls',
        'ejections', 'disqualifications',
    ];

    public function __construct(
        private readonly PlayerHistoryRepository $repository,
    ) {}

    /**
     * Validate then upsert a history row. Returns the upserted model.
     *
     * @param  int                   $playerId  Comes from the route — never from file data
     * @param  array<string, mixed>  $row       Raw data from xlsx or form
     *
     * @throws RuntimeException  on validation failure (row is skipped, not fatal)
     */
    public function execute(int $playerId, array $row): PlayerHistory
    {
        $playingTeamId  = $this->requirePositiveInt($row, 'playing_team_id');
        $opponentTeamId = $this->requirePositiveInt($row, 'opponent_team_id');

        if (! DB::table('players')->where('id', $playerId)->exists()) {
            throw new RuntimeException("player_id {$playerId} does not exist.");
        }

        if (! DB::table('teams')->where('id', $playingTeamId)->exists()) {
            throw new RuntimeException("playing_team_id {$playingTeamId} does not exist.");
        }

        if (! DB::table('teams')->where('id', $opponentTeamId)->exists()) {
            throw new RuntimeException("opponent_team_id {$opponentTeamId} does not exist.");
        }

        if ($playingTeamId === $opponentTeamId) {
            throw new RuntimeException("playing_team_id and opponent_team_id must differ (got {$playingTeamId} for both).");
        }

        $gameDate = trim((string) ($row['game_date'] ?? ''));

        if ($gameDate === '' || ! strtotime($gameDate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $gameDate)) {
            throw new RuntimeException("game_date '{$gameDate}' is not a valid YYYY-MM-DD date.");
        }

        $intStats = [];

        foreach (self::INT_STAT_COLUMNS as $col) {
            $raw = $row[$col] ?? null;

            if ($raw === null || $raw === '') {
                $intStats[$col] = null;
                continue;
            }

            $val = filter_var($raw, FILTER_VALIDATE_INT);

            if ($val === false || $val < 0) {
                throw new RuntimeException("'{$col}' must be a non-negative integer (got '{$raw}').");
            }

            $intStats[$col] = $val;
        }

        $minutesPlayed = isset($row['minutes_played']) && $row['minutes_played'] !== ''
            ? (float) $row['minutes_played']
            : null;

        if ($minutesPlayed !== null && $minutesPlayed < 0) {
            throw new RuntimeException("'minutes_played' must be non-negative (got '{$minutesPlayed}').");
        }

        $isStarted = $this->parseBool($row['is_started'] ?? '0');

        return $this->repository->upsert([
            'player_id'        => $playerId,
            'game_date'        => $gameDate,
            'playing_team_id'  => $playingTeamId,
            'opponent_team_id' => $opponentTeamId,
            'position_played'  => isset($row['position_played']) && $row['position_played'] !== ''
                ? trim((string) $row['position_played'])
                : null,
            'minutes_played'   => $minutesPlayed,
            'is_started'       => $isStarted,
            'notes'            => isset($row['notes']) && $row['notes'] !== ''
                ? trim((string) $row['notes'])
                : null,
            ...$intStats,
        ]);
    }

    private function requirePositiveInt(array $row, string $key): int
    {
        $val = filter_var($row[$key] ?? null, FILTER_VALIDATE_INT);

        if ($val === false || $val <= 0) {
            throw new RuntimeException("'{$key}' must be a positive integer (got '{$row[$key]}').");
        }

        return $val;
    }

    private function parseBool(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true'], strict: true);
    }
}
