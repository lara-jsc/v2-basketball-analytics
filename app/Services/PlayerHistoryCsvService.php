<?php

namespace App\Services;

use App\Jobs\PlayerHistoryImportJob;
use Carbon\CarbonInterface;

class PlayerHistoryCsvService
{
    public function generateExportContent(iterable $histories): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return implode(',', PlayerHistoryImportJob::HEADERS)."\n";
        }

        fputcsv($stream, PlayerHistoryImportJob::HEADERS);

        foreach ($histories as $history) {
            fputcsv($stream, [
                $this->formatDate($this->value($history, 'game_date')),
                $this->scalar($this->value($history, 'opponent_team_id')),
                $this->scalar($this->value($history, 'position_played')),
                $this->scalar($this->value($history, 'minutes_played')),
                $this->scalar($this->value($history, 'points')),
                $this->scalar($this->value($history, 'field_goals_made')),
                $this->scalar($this->value($history, 'field_goals_attempted')),
                $this->scalar($this->value($history, 'three_pointers_made')),
                $this->scalar($this->value($history, 'three_pointers_attempted')),
                $this->scalar($this->value($history, 'free_throws_made')),
                $this->scalar($this->value($history, 'free_throws_attempted')),
                $this->scalar($this->value($history, 'offensive_rebounds')),
                $this->scalar($this->value($history, 'defensive_rebounds')),
                $this->scalar($this->value($history, 'rebounds')),
                $this->scalar($this->value($history, 'assists')),
                $this->scalar($this->value($history, 'steals')),
                $this->scalar($this->value($history, 'blocks')),
                $this->scalar($this->value($history, 'turnovers')),
                $this->scalar($this->value($history, 'personal_fouls')),
                $this->scalar($this->value($history, 'flagrant_fouls')),
                $this->scalar($this->value($history, 'technical_fouls')),
                $this->scalar($this->value($history, 'ejections')),
                $this->scalar($this->value($history, 'disqualifications')),
                $this->value($history, 'is_started') ? '1' : '0',
                $this->scalar($this->value($history, 'notes')),
            ]);
        }

        rewind($stream);

        $content = stream_get_contents($stream);
        fclose($stream);

        return $content === false
            ? implode(',', PlayerHistoryImportJob::HEADERS)."\n"
            : $content;
    }

    /**
     * @param  object|array<string, mixed>  $item
     */
    private function value(object|array $item, string $key): mixed
    {
        if (is_array($item)) {
            return $item[$key] ?? null;
        }

        return $item->{$key} ?? null;
    }

    private function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    private function formatDate(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        return $this->scalar($value);
    }
}
