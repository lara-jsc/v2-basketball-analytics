import { AlertRow } from './AlertRow';
import type { LiveGameAlert, Player, Team } from '@/types';
import { BellRing } from 'lucide-react';

interface AlertsPanelProps {
    alerts: LiveGameAlert[];
    players: Player[];
    teams: Team[];
    viewerSide: 'home' | 'opponent' | null;
    homeTeamId: number;
    opponentTeamId: number;
    className?: string;
}

interface AlertGroup {
    key: string;
    heading: string;
    alerts: LiveGameAlert[];
}

export function AlertsPanel({ alerts, players, teams, viewerSide, homeTeamId, opponentTeamId, className = '' }: AlertsPanelProps) {
    const groups = buildGroups(alerts, teams, viewerSide, homeTeamId, opponentTeamId);

    return (
        <section
            className={`flex min-h-0 flex-col overflow-hidden rounded-lg border border-border bg-card ${className}`}
            aria-labelledby="alerts-heading"
        >
            <div className="flex shrink-0 items-center justify-between border-b border-border px-4 py-2.5">
                <h2
                    id="alerts-heading"
                    className="flex items-center gap-2 text-sm font-bold uppercase tracking-[0.1em] text-foreground"
                >
                    <BellRing size={16} className="live-text-warn" /> Coach alerts
                </h2>
                <span className="text-xs text-muted-foreground">{alerts.length} active</span>
            </div>
            <div
                tabIndex={0}
                aria-label="Coach alerts, grouped by team"
                className="min-h-0 flex-1 overflow-y-auto focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-cyan-300"
            >
                {groups.map((group) => (
                    <div key={group.key}>
                        <p className="sticky top-0 z-10 border-b border-border/70 bg-card px-4 py-1.5 text-[11px] font-bold uppercase tracking-[0.12em] text-muted-foreground">
                            {group.heading}
                        </p>
                        {group.alerts.map((alert) => (
                            <AlertRow key={alert.id} alert={alert} players={players} />
                        ))}
                    </div>
                ))}
                {alerts.length === 0 && (
                    <p className="p-4 text-sm text-muted-foreground">No rule-based alerts are active.</p>
                )}
            </div>
        </section>
    );
}

function buildGroups(
    alerts: LiveGameAlert[],
    teams: Team[],
    viewerSide: 'home' | 'opponent' | null,
    homeTeamId: number,
    opponentTeamId: number,
): AlertGroup[] {
    const teamName = (teamId: number): string => {
        const team = teams.find((candidate) => candidate.id === teamId);

        return team?.name ?? (teamId === homeTeamId ? 'Home' : 'Opponent');
    };
    const teamCode = (teamId: number): string => {
        const team = teams.find((candidate) => candidate.id === teamId);

        return team?.code ?? teamName(teamId);
    };

    const ownTeamId = viewerSide === 'opponent' ? opponentTeamId : homeTeamId;
    const otherTeamId = ownTeamId === homeTeamId ? opponentTeamId : homeTeamId;
    const orderedTeamIds = viewerSide === null ? [homeTeamId, opponentTeamId] : [ownTeamId, otherTeamId];

    const groups: AlertGroup[] = orderedTeamIds.map((teamId) => ({
        key: `team-${teamId}`,
        heading: viewerSide !== null && teamId === ownTeamId ? `Your team — ${teamCode(teamId)}` : teamName(teamId),
        alerts: alerts.filter((alert) => alert.team_id === teamId),
    }));

    const unassigned = alerts.filter(
        (alert) => alert.team_id === null || !orderedTeamIds.includes(alert.team_id),
    );

    if (unassigned.length > 0) {
        groups.push({ key: 'unassigned', heading: 'Unassigned', alerts: unassigned });
    }

    return groups.filter((group) => group.alerts.length > 0);
}
