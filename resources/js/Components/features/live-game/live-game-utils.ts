import type { LiveGameEvent, Player, Team } from '@/types';

export function playerName(player: Player): string {
    return `${player.first_name} ${player.last_name}`;
}

export function clockLabel(seconds: number): string {
    const safeSeconds = Math.max(0, seconds);
    return `${Math.floor(safeSeconds / 60)}:${String(safeSeconds % 60).padStart(2, '0')}`;
}

export function eventLabel(event: LiveGameEvent, players: Player[], teams: Team[] = []): string {
    // A timeout carries no player, so the calling team comes from the payload the
    // recorder stamps — team_scope 'own' is relative to whoever recorded it.
    if (event.type === 'timeout') {
        const teamId = typeof event.payload.team_id === 'number' ? event.payload.team_id : null;
        const team = teamId !== null ? teams.find((candidate) => candidate.id === teamId) : undefined;

        return team ? `timeout · ${team.code ?? team.name}` : 'timeout';
    }

    const player = event.player_id ? players.find((candidate) => candidate.id === event.player_id) : undefined;
    const points = typeof event.payload.points === 'number' ? ` ${event.payload.points}PT` : '';
    const kind = typeof event.payload.kind === 'string' ? ` ${event.payload.kind}` : '';
    const name = player ? ` ${playerName(player)}` : '';

    return `${event.type.replaceAll('_', ' ')}${points}${kind}${name}`;
}
