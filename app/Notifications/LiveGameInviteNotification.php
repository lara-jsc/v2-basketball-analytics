<?php

namespace App\Notifications;

use App\Models\LiveGame;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class LiveGameInviteNotification extends Notification
{
    use Queueable;

    public const KIND_OPPONENT_SETUP = 'opponent_setup';

    public const KIND_HOME_ASSIGNED = 'home_assigned';

    public const KIND_HOME_TEAM = 'home_team';

    public function __construct(
        public readonly LiveGame $liveGame,
        public readonly string $kind,
        public readonly string $homeTeamName,
        public readonly string $opponentTeamName,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if (app()->runningUnitTests()) {
            return ['database'];
        }

        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'live_game_id' => $this->liveGame->id,
            'kind' => $this->kind,
            'title' => $this->title(),
            'body' => $this->body(),
            'home_team_name' => $this->homeTeamName,
            'opponent_team_name' => $this->opponentTeamName,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return (new BroadcastMessage($this->toArray($notifiable)))
            ->onConnection('sync');
    }

    public function broadcastType(): string
    {
        return 'live-game.invite';
    }

    private function title(): string
    {
        return match ($this->kind) {
            self::KIND_OPPONENT_SETUP => 'Live game ready to set up',
            self::KIND_HOME_ASSIGNED => "You're assigned to a live game",
            default => 'Your team started a live game',
        };
    }

    private function body(): string
    {
        $matchup = "{$this->homeTeamName} vs {$this->opponentTeamName}";

        return match ($this->kind) {
            self::KIND_OPPONENT_SETUP => "{$matchup} — open the game and submit your starting five.",
            self::KIND_HOME_ASSIGNED => "{$matchup} — you're on the pad. Open the game when you're ready.",
            default => "{$matchup} — open the live game to follow setup.",
        };
    }
}
