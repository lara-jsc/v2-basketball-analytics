import { HalfCourt, type HalfCourtZoneState } from '@/Components/features/analytics/HalfCourt';
import { MIN_ZONE_ATTEMPTS, SHOT_ZONES, type ShotZoneKey } from '@/Components/features/live-game/event-catalog';
import { AlertTriangle } from 'lucide-react';

/**
 * Shape of one zone from ShotZoneService::profileFor().
 */
interface ZoneData {
    label: string;
    made: number;
    attempted: number;
    percentage: number;
    points_per_shot: number;
    has_enough_data: boolean;
}

/**
 * The zone the player attempts from most often. Null until any shot is located.
 */
interface TopZone {
    key: ShotZoneKey;
    label: string;
    attempt_share: number;
    percentage: number;
    has_enough_data: boolean;
}

export interface ShotZoneProfile {
    zones: Record<ShotZoneKey, ZoneData>;
    top_zone: TopZone | null;
    located_shots: number;
    live_located_shots: number;
    live_total_shots: number;
    total_shots: number;
    profile_vs_history_ok: boolean;
}

interface ShotZoneCourtProps {
    profile: ShotZoneProfile;
    playerName: string;
    className?: string;
    compact?: boolean;
}

/**
 * PPS color scale: fixed domain 0.7 → 1.4, cold (slate) to hot (amber).
 * Fixed domain ensures the same color means the same efficiency on both courts.
 */
function ppsToFill(pps: number): string {
    const min = 0.7;
    const max = 1.4;
    const t = Math.max(0, Math.min(1, (pps - min) / (max - min)));

    // Cold: oklch(0.52 0.12 240) → Hot: oklch(0.72 0.20 55)
    const l = 0.52 + t * 0.20;
    const c = 0.12 + t * 0.08;
    const h = 240 - t * 185; // 240 (blue) → 55 (amber)

    return `oklch(${l.toFixed(3)} ${c.toFixed(3)} ${h.toFixed(1)})`;
}

function ZoneLabel({ zone }: { zone: ZoneData }) {
    if (!zone.has_enough_data) {
        return <>{zone.made}/{zone.attempted}</>;
    }
    return (
        <>
            {zone.made}/{zone.attempted}
            {'\n'}{zone.percentage}%
            {'\n'}{zone.points_per_shot} PPS
        </>
    );
}

export function ShotZoneCourt({ profile, playerName, className, compact = false }: ShotZoneCourtProps) {
    const hasAnyData = profile.located_shots > 0;

    const zones: Partial<Record<ShotZoneKey, HalfCourtZoneState>> = Object.fromEntries(
        SHOT_ZONES.map((z) => {
            const data = profile.zones[z.key];
            if (!data) return [z.key, { fill: 'transparent' }];

            const fill = data.has_enough_data
                ? ppsToFill(data.points_per_shot)
                : 'oklch(0.55 0.03 250)'; // neutral gray

            return [
                z.key,
                {
                    fill,
                    label: <ZoneLabel zone={data} />,
                    highlighted: profile.top_zone?.key === z.key,
                },
            ];
        }),
    );

    return (
        <div className={className}>
            {/* Player header */}
            <p className={compact ? 'mb-1 text-xs font-bold text-foreground' : 'mb-2 text-sm font-bold text-foreground'}>
                {playerName}
            </p>

            {/* Shot tendency — the outlined zone on the court below */}
            {hasAnyData && profile.top_zone && (
                <p className={compact ? 'mb-2 text-[11px] leading-5 font-semibold' : 'mb-2 text-xs font-semibold'}>
                    <span className="uppercase tracking-wide text-muted-foreground">Usually shoots from</span>{' '}
                    <span className="text-foreground">
                        {profile.top_zone.label} · {profile.top_zone.attempt_share}% of shots ·{' '}
                        {profile.top_zone.percentage}% make
                    </span>
                    {!profile.top_zone.has_enough_data && (
                        <span className="font-normal text-muted-foreground"> · small sample</span>
                    )}
                </p>
            )}

            {!hasAnyData ? (
                <div className="flex flex-col items-center gap-2 rounded-lg border border-border bg-muted/20 p-3 text-center">
                    <HalfCourt zones={{}} className={compact ? 'w-full max-w-[280px] opacity-30' : 'max-h-48 w-full opacity-30'} />
                    <p className="text-xs text-muted-foreground">No shot locations recorded yet</p>
                </div>
            ) : (
                <div className={compact ? 'rounded-lg border border-border bg-muted/20 p-2' : 'rounded-lg border border-border bg-muted/20 p-2'}>
                    <HalfCourt zones={zones} className={compact ? 'mx-auto w-full max-w-[280px]' : 'w-full'} />
                </div>
            )}

            {/* PPS color scale legend */}
            {hasAnyData && !compact && (
                <div className="mt-2 flex items-center justify-between gap-2 px-1">
                    <span className="text-xs text-muted-foreground">0.7 PPS</span>
                    <div
                        className="h-2 flex-1 rounded-full"
                        style={{
                            background: 'linear-gradient(to right, oklch(0.52 0.12 240), oklch(0.62 0.15 150), oklch(0.72 0.20 55))',
                        }}
                        aria-hidden="true"
                    />
                    <span className="text-xs text-muted-foreground">1.4 PPS</span>
                </div>
            )}

            {/* Coverage footer */}
            {hasAnyData && profile.live_total_shots > 0 && !compact && (
                <p className="mt-1 text-xs text-muted-foreground">
                    {profile.live_located_shots} of {profile.live_total_shots} live shots located
                </p>
            )}

            {/* Profile mismatch warning */}
            {!profile.profile_vs_history_ok && !compact && (
                <div className="mt-2 flex items-start gap-1.5 rounded-md border border-amber-400/40 bg-amber-50 px-3 py-2 dark:bg-amber-400/10">
                    <AlertTriangle size={13} className="mt-0.5 shrink-0 text-amber-600 dark:text-amber-400" />
                    <p className="text-xs text-amber-700 dark:text-amber-300">
                        Season profile no longer matches box-score totals. Re-import to fix.
                    </p>
                </div>
            )}
        </div>
    );
}
