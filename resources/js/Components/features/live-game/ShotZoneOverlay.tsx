import { HalfCourt, type HalfCourtZoneState } from '@/Components/features/analytics/HalfCourt';
import { MIN_ZONE_ATTEMPTS, SHOT_ZONES, zonesForPoints, type ShotZoneKey } from '@/Components/features/live-game/event-catalog';
import axios from 'axios';
import { AlertCircle, RotateCcw, X } from 'lucide-react';
import { useRef, useState } from 'react';

interface ShotZoneOverlayProps {
    liveGameId: number;
    eventId: number;
    points: 2 | 3;
    /** e.g. "A. Johnson · 3PT make" — shown in the context chip */
    contextLabel: string;
    onDone: () => void;
}

type Status = 'idle' | 'saving' | 'error';

/**
 * Non-blocking pad-anchored sheet shown after every field-goal record.
 * The shot is already saved — this only attaches a zone. Skipping leaves the shot intact.
 */
export function ShotZoneOverlay({ liveGameId, eventId, points, contextLabel, onDone }: ShotZoneOverlayProps) {
    const [status, setStatus] = useState<Status>('idle');
    const [errorMsg, setErrorMsg] = useState<string | null>(null);
    const [selectedZone, setSelectedZone] = useState<ShotZoneKey | null>(null);
    const skipRef = useRef<HTMLButtonElement>(null);

    const eligibleZones = zonesForPoints(points);
    const eligibleKeys = new Set(eligibleZones.map((z) => z.key));

    const zones: Partial<Record<ShotZoneKey, HalfCourtZoneState>> = Object.fromEntries(
        SHOT_ZONES.map((z) => [
            z.key,
            {
                fill: eligibleKeys.has(z.key) ? 'oklch(0.62 0.15 250)' : 'transparent',
                disabled: !eligibleKeys.has(z.key),
                label: eligibleKeys.has(z.key) ? z.label : undefined,
            },
        ]),
    );

    async function attachZone(zone: ShotZoneKey) {
        if (status === 'saving') return;
        setSelectedZone(zone);
        setStatus('saving');
        setErrorMsg(null);

        try {
            await axios.patch(route('live-games.events.zone', { liveGame: liveGameId, event: eventId }), {
                zone,
            });
            onDone();
        } catch (err) {
            setStatus('error');
            const msg =
                axios.isAxiosError(err) && err.response?.data?.message
                    ? (err.response.data.message as string)
                    : 'Failed to save the zone. Try again or skip.';
            setErrorMsg(msg);
        }
    }

    async function retry() {
        if (selectedZone) {
            await attachZone(selectedZone);
        }
    }

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label="Shot location"
            className="fixed inset-x-0 bottom-0 z-50 rounded-t-2xl border-t border-border bg-card p-4 shadow-2xl sm:inset-x-auto sm:right-4 sm:bottom-4 sm:max-w-sm sm:rounded-2xl sm:border"
        >
            {/* Header */}
            <div className="mb-3 flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-sm font-bold text-foreground">Where was the shot taken?</h2>
                    <p className="mt-0.5 truncate text-xs text-muted-foreground">{contextLabel}</p>
                </div>
                <button
                    type="button"
                    onClick={onDone}
                    aria-label="Skip — do not record a zone"
                    className="flex min-h-8 min-w-8 cursor-pointer items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                >
                    <X size={16} />
                </button>
            </div>

            {/* Court */}
            <div className="rounded-lg border border-border bg-muted/20 p-2">
                <HalfCourt
                    zones={zones}
                    onZoneClick={status !== 'saving' ? attachZone : undefined}
                    className="max-h-64"
                />
            </div>

            {/* Error state */}
            {status === 'error' && errorMsg && (
                <div className="mt-3 flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2">
                    <AlertCircle size={14} className="mt-0.5 shrink-0 text-destructive" />
                    <p className="text-xs text-destructive">{errorMsg}</p>
                </div>
            )}

            {/* Zone buttons (secondary targets for small corner zones) */}
            <div className="mt-3 grid grid-cols-2 gap-1.5">
                {eligibleZones.map((zone) => (
                    <button
                        key={zone.key}
                        type="button"
                        onClick={() => void attachZone(zone.key)}
                        disabled={status === 'saving'}
                        className="flex min-h-10 cursor-pointer items-center justify-center rounded-md border border-border bg-muted/30 px-2 text-xs font-semibold text-foreground transition-colors hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {zone.label}
                    </button>
                ))}
            </div>

            {/* Footer actions */}
            <div className="mt-3 flex items-center justify-between gap-2">
                {status === 'error' ? (
                    <button
                        type="button"
                        onClick={() => void retry()}
                        className="flex min-h-10 cursor-pointer items-center gap-1.5 rounded-md border border-border px-3 text-xs font-semibold text-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        <RotateCcw size={13} /> Retry
                    </button>
                ) : (
                    <span className="text-xs text-muted-foreground">Tap a zone or a button above</span>
                )}

                <button
                    ref={skipRef}
                    type="button"
                    onClick={onDone}
                    className="flex min-h-10 cursor-pointer items-center rounded-md bg-amber-400 px-4 text-xs font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                >
                    Skip
                </button>
            </div>
        </div>
    );
}
