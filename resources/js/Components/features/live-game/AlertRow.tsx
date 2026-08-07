import { clockLabel } from './live-game-utils';
import type { LiveGameAlert, Player } from '@/types';

const TYPE_LABELS: Record<string, string> = {
    hot_player: 'Hot player',
    cold_player: 'Cold player',
    foul_trouble: 'Foul trouble',
    opponent_run: 'Opponent run',
    team_drought: 'Team drought',
    timeout_prompt: 'Timeout prompt',
    substitution_prompt: 'Substitution prompt',
};

const SEVERITY_CLASSES: Record<string, string> = {
    info: 'live-badge-info',
    warning: 'live-badge-warn',
};

export function AlertRow({ alert, players }: { alert: LiveGameAlert; players: Player[] }) {
    const player = alert.player_id ? players.find((candidate) => candidate.id === alert.player_id) : undefined;
    const disqualified = alert.context.disqualified === true;

    return (
        <div className="border-b border-border/70 px-4 py-3 last:border-b-0">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-semibold text-foreground">{alert.message}</p>
                <span
                    className={`shrink-0 rounded px-2 py-0.5 text-xs font-bold uppercase ${
                        disqualified
                            ? 'live-badge-danger'
                            : (SEVERITY_CLASSES[alert.severity] ?? 'bg-muted text-muted-foreground')
                    }`}
                >
                    {disqualified ? 'DQ' : alert.severity}
                </span>
            </div>
            <p className="mt-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                {TYPE_LABELS[alert.type] ?? alert.type.replaceAll('_', ' ')} · Q{alert.period}{' '}
                {clockLabel(alert.clock_seconds_remaining)}
                {player ? ` · ${player.first_name} ${player.last_name}` : ''}
            </p>
        </div>
    );
}
