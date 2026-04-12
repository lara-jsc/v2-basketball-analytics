import { formatEff, formatPercent, formatPlusMinus } from '@/lib/statFormatters';

// ─── Types ────────────────────────────────────────────────────────────────────

interface AdvancedStats {
    /** Efficiency Rating (raw float, e.g. 31.0). Null until computed. */
    eff: number | null;
    /** Effective Field Goal % already in percentage form (e.g. 69.4). Null until computed. */
    efg_percent: number | null;
    /** True Shooting % already in percentage form (e.g. 65.1). Null until computed. */
    ts_percent: number | null;
    /** Plus/Minus signed value (e.g. 14, -3, 0). Null until computed. */
    plus_minus: number | null;
}

interface PlayerAdvancedStatsBannerProps {
    stat: AdvancedStats;
    /** Show skeleton pulse placeholders while the API response is loading. */
    loading?: boolean;
    /** Inline error message — suppresses values but does not crash the page. */
    error?: string;
}

// ─── League-average thresholds for color coding ───────────────────────────────

const THRESHOLDS = {
    eff:         15.0,
    efg_percent: 53.5,
    ts_percent:  57.5,
    plus_minus:  0,
} as const;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function valueColor(value: number | null, threshold: number): string {
    if (value === null) return 'text-muted-foreground';
    return value >= threshold ? 'text-green-400' : 'text-red-400';
}

// ─── Sub-component: single stat card ─────────────────────────────────────────

interface StatCardProps {
    testId: string;
    label: string;
    description: string;
    displayValue: string;
    colorClass: string;
    isNull: boolean;
}

function StatCard({ testId, label, description, displayValue, colorClass, isNull }: StatCardProps) {
    return (
        <div className="relative flex flex-col items-center justify-center rounded-xl border border-border bg-card px-4 py-5 text-center transition-all hover:border-border/80 hover:bg-card/80 group">
            {/* Subtle inner glow on hover */}
            <div className="pointer-events-none absolute inset-0 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity bg-[radial-gradient(ellipse_at_center,rgba(249,160,27,0.04),transparent_70%)]" />

            {/* Stat label */}
            <span className="font-ui text-[10px] font-bold tracking-widest text-muted-foreground uppercase mb-2">
                {label}
            </span>

            {/* Primary value — dominant visual element */}
            {isNull ? (
                <span
                    data-testid={testId}
                    className="font-display text-3xl font-bold leading-none text-muted-foreground/50"
                    title="Stats will be available after the first game"
                >
                    —
                </span>
            ) : (
                <span
                    data-testid={testId}
                    className={`font-display text-3xl font-bold leading-none ${colorClass}`}
                >
                    {displayValue}
                </span>
            )}

            {/* Description */}
            <span className="font-ui text-[10px] text-muted-foreground/70 mt-2 max-w-[10rem] leading-tight">
                {description}
            </span>
        </div>
    );
}

// ─── Sub-component: skeleton card ────────────────────────────────────────────

function SkeletonCard() {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-border bg-card px-4 py-5 text-center gap-2">
            <div className="h-3 w-12 rounded-md bg-muted animate-pulse" />
            <div className="h-9 w-16 rounded-lg bg-muted animate-pulse" />
            <div className="h-2.5 w-24 rounded-md bg-muted/60 animate-pulse" />
        </div>
    );
}

// ─── Main component ───────────────────────────────────────────────────────────

export function PlayerAdvancedStatsBanner({
    stat,
    loading = false,
    error,
}: PlayerAdvancedStatsBannerProps) {
    if (loading) {
        return (
            <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                <SkeletonCard />
                <SkeletonCard />
                <SkeletonCard />
                <SkeletonCard />
            </div>
        );
    }

    if (error) {
        return (
            <div className="flex items-center justify-center rounded-xl border border-destructive/30 bg-destructive/5 px-5 py-4">
                <p className="font-ui text-sm text-destructive">{error}</p>
            </div>
        );
    }

    const cards = [
        {
            testId:       'stat-eff',
            label:        'EFF',
            description:  'Measures overall contribution per game',
            displayValue: formatEff(stat.eff),
            colorClass:   valueColor(stat.eff, THRESHOLDS.eff),
            isNull:       stat.eff === null,
        },
        {
            testId:       'stat-efg',
            label:        'eFG%',
            description:  'Adjusts for 3-point shot value',
            displayValue: formatPercent(stat.efg_percent),
            colorClass:   valueColor(stat.efg_percent, THRESHOLDS.efg_percent),
            isNull:       stat.efg_percent === null,
        },
        {
            testId:       'stat-ts',
            label:        'TS%',
            description:  'True scoring efficiency with free throws',
            displayValue: formatPercent(stat.ts_percent),
            colorClass:   valueColor(stat.ts_percent, THRESHOLDS.ts_percent),
            isNull:       stat.ts_percent === null,
        },
        {
            testId:       'stat-plus-minus',
            label:        '+/-',
            description:  'Net points per game while on court',
            displayValue: formatPlusMinus(stat.plus_minus),
            colorClass:   valueColor(stat.plus_minus, THRESHOLDS.plus_minus),
            isNull:       stat.plus_minus === null,
        },
    ] as const;

    return (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
            {cards.map((card) => (
                <StatCard key={card.testId} {...card} />
            ))}
        </div>
    );
}
