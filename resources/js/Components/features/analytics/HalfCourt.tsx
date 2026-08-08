import type { ShotZoneKey } from '@/Components/features/live-game/event-catalog';
import type { KeyboardEvent } from 'react';

export interface HalfCourtZoneState {
    fill: string;
    disabled?: boolean;
    label?: React.ReactNode;
}

export interface HalfCourtProps {
    zones: Partial<Record<ShotZoneKey, HalfCourtZoneState>>;
    onZoneClick?: (zone: ShotZoneKey) => void;
    className?: string;
}

/**
 * Half-court SVG — hoop at the bottom, five shot zones.
 * viewBox 500×470.
 *
 * Zone geometry (all in SVG units):
 *   - Paint:           centered key rectangle, 170 wide, 190 tall, from baseline
 *   - Mid-range:       inside the 3PT arc, outside the paint
 *   - Corner 3 Left:   left baseline strip beyond the arc corner (x < 65)
 *   - Corner 3 Right:  right baseline strip beyond the arc corner (x > 435)
 *   - Above break 3:   everything else beyond the arc
 *
 * The arc is a true FIBA-approximate arc: radius ~237px (scaled to 500px court width).
 * Corner 3 lines are at x = 65 (left) and x = 435 (right), running from y=470 to y=328.
 */

// Court dimensions (all SVG units, 500 wide, 470 tall, hoop at bottom)
const W = 500;
const H = 470;
const CX = 250; // center-x
const HOOP_Y = 440; // hoop y position
const HOOP_RADIUS = 10;
const BACKBOARD_Y = 425;
const BACKBOARD_HALF = 30;

// Paint (key) rectangle
const KEY_W = 170;
const KEY_H = 190;
const KEY_X = CX - KEY_W / 2;
const KEY_Y = HOOP_Y - KEY_H; // top of key

// 3PT arc
const ARC_RADIUS = 237;
const CORNER_X_LEFT = 65;
const CORNER_X_RIGHT = W - CORNER_X_LEFT;
const CORNER_LINE_TOP_Y = H - 143; // corner 3 line reaches y=327 in court units

// FT circle (top half, above key)
const FT_CIRCLE_CY = KEY_Y;
const FT_CIRCLE_R = KEY_W / 2; // matches key width

// Arc center aligns with hoop
const ARC_CY = HOOP_Y;

// Compute arc start/end x for the corner connection
const arcHalfAngle = Math.asin((CX - CORNER_X_LEFT) / ARC_RADIUS); // angle from vertical
const arcX_left = CX - ARC_RADIUS * Math.sin(arcHalfAngle); // = CORNER_X_LEFT
const arcX_right = CX + ARC_RADIUS * Math.sin(arcHalfAngle); // = CORNER_X_RIGHT
const arcY_corner = HOOP_Y - ARC_RADIUS * Math.cos(arcHalfAngle);

/**
 * Clip-path for the arc region (everything inside the 3PT arc above corner lines).
 * Used to cut mid-range from the arc interior.
 */

type ZoneConfig = {
    key: ShotZoneKey;
    path: string;
    labelX: number;
    labelY: number;
};

const ZONES: ZoneConfig[] = [
    {
        key: 'paint',
        // Key rectangle from baseline to top of key
        path: `M${KEY_X},${H} L${KEY_X},${KEY_Y} L${KEY_X + KEY_W},${KEY_Y} L${KEY_X + KEY_W},${H} Z`,
        labelX: CX,
        labelY: HOOP_Y - KEY_H / 2 + 20,
    },
    {
        key: 'mid_range',
        // Inside 3PT arc, outside paint. Four sub-areas: left, right, above key.
        // Shape: arc from (CORNER_X_LEFT, arcY_corner) to (CORNER_X_RIGHT, arcY_corner),
        // cut by the key rectangle, clipped to y >= arcY_corner (corner line level).
        // We define the path as the arc bounding region minus the paint.
        // We'll use a compound path:
        //   - Outer: full arc (large) + corner lines + baseline
        //   - Inner cut (paint rectangle) via even-odd fill rule
        path: [
            // Outer boundary: baseline left → corner line left → arc → corner line right → baseline right
            `M${CORNER_X_LEFT},${H}`,
            `L${CORNER_X_LEFT},${arcY_corner}`,
            `A${ARC_RADIUS},${ARC_RADIUS} 0 0,1 ${CORNER_X_RIGHT},${arcY_corner}`,
            `L${CORNER_X_RIGHT},${H}`,
            `Z`,
            // Inner cut: paint rectangle (even-odd punches a hole)
            `M${KEY_X},${H}`,
            `L${KEY_X},${KEY_Y}`,
            `L${KEY_X + KEY_W},${KEY_Y}`,
            `L${KEY_X + KEY_W},${H}`,
            `Z`,
        ].join(' '),
        labelX: CX,
        labelY: arcY_corner - 20,
    },
    {
        key: 'corner_3_left',
        path: `M0,${H} L0,${CORNER_LINE_TOP_Y} L${CORNER_X_LEFT},${CORNER_LINE_TOP_Y} L${CORNER_X_LEFT},${H} Z`,
        labelX: CORNER_X_LEFT / 2,
        labelY: (H + CORNER_LINE_TOP_Y) / 2,
    },
    {
        key: 'corner_3_right',
        path: `M${CORNER_X_RIGHT},${H} L${CORNER_X_RIGHT},${CORNER_LINE_TOP_Y} L${W},${CORNER_LINE_TOP_Y} L${W},${H} Z`,
        labelX: (W + CORNER_X_RIGHT) / 2,
        labelY: (H + CORNER_LINE_TOP_Y) / 2,
    },
    {
        key: 'above_break_3',
        // Everything from y=0 to y=CORNER_LINE_TOP_Y, plus the above-arc region
        // from corner line level to the arc break on both sides
        path: [
            `M0,0`,
            `L${W},0`,
            `L${W},${CORNER_LINE_TOP_Y}`,
            `L${CORNER_X_RIGHT},${CORNER_LINE_TOP_Y}`,
            `A${ARC_RADIUS},${ARC_RADIUS} 0 0,0 ${CORNER_X_LEFT},${CORNER_LINE_TOP_Y}`,
            `L0,${CORNER_LINE_TOP_Y}`,
            `Z`,
        ].join(' '),
        labelX: CX,
        labelY: arcY_corner / 2 + 20,
    },
];

function ZonePath({
    config,
    state,
    interactive,
    onClick,
}: {
    config: ZoneConfig;
    state: HalfCourtZoneState | undefined;
    interactive: boolean;
    onClick?: (zone: ShotZoneKey) => void;
}) {
    const fill = state?.fill ?? 'transparent';
    const disabled = !interactive || state?.disabled;
    const isClickable = interactive && !disabled && onClick;

    function handleKey(e: KeyboardEvent<SVGPathElement>) {
        if (isClickable && (e.key === 'Enter' || e.key === ' ')) {
            e.preventDefault();
            onClick!(config.key);
        }
    }

    const label = state?.label;

    return (
        <g>
            <path
                d={config.path}
                fill={fill}
                fillOpacity={fill === 'transparent' ? 0 : 0.75}
                fillRule="evenodd"
                stroke="none"
                style={{ pointerEvents: disabled ? 'none' : 'auto' }}
                role={isClickable ? 'button' : undefined}
                tabIndex={isClickable ? 0 : undefined}
                aria-label={isClickable ? String(label ?? config.key) : undefined}
                aria-disabled={disabled || undefined}
                onClick={isClickable ? () => onClick!(config.key) : undefined}
                onKeyDown={handleKey}
                className={isClickable ? 'cursor-pointer outline-none focus-visible:opacity-80' : undefined}
            />
            {label && (
                <text
                    x={config.labelX}
                    y={config.labelY}
                    textAnchor="middle"
                    dominantBaseline="middle"
                    fontSize={14}
                    fontWeight={600}
                    fill="currentColor"
                    style={{ pointerEvents: 'none', userSelect: 'none' }}
                >
                    {label}
                </text>
            )}
        </g>
    );
}

export function HalfCourt({ zones, onZoneClick, className }: HalfCourtProps) {
    const interactive = onZoneClick !== undefined;

    return (
        <svg
            viewBox={`0 0 ${W} ${H}`}
            role="img"
            aria-label="Half-court shot chart"
            className={className}
            style={{ width: '100%', height: 'auto', display: 'block' }}
        >
            {/* ── Zone fills (behind lines) ───────────────────── */}
            {ZONES.map((config) => (
                <ZonePath
                    key={config.key}
                    config={config}
                    state={zones[config.key]}
                    interactive={interactive}
                    onClick={onZoneClick}
                />
            ))}

            {/* ── Court lines (currentColor, low opacity for theming) ── */}
            <g stroke="currentColor" strokeOpacity={0.25} fill="none" strokeWidth={2} style={{ pointerEvents: 'none' }}>
                {/* Outer boundary */}
                <rect x={0} y={0} width={W} height={H} />

                {/* Paint / key */}
                <rect x={KEY_X} y={KEY_Y} width={KEY_W} height={KEY_H} />

                {/* Free-throw circle (top half) */}
                <path d={`M${KEY_X},${FT_CIRCLE_CY} A${FT_CIRCLE_R},${FT_CIRCLE_R} 0 0,0 ${KEY_X + KEY_W},${FT_CIRCLE_CY}`} />
                {/* FT circle bottom half (dashed) */}
                <path d={`M${KEY_X},${FT_CIRCLE_CY} A${FT_CIRCLE_R},${FT_CIRCLE_R} 0 0,1 ${KEY_X + KEY_W},${FT_CIRCLE_CY}`} strokeDasharray="8 6" />

                {/* 3PT arc */}
                <line x1={CORNER_X_LEFT} y1={H} x2={CORNER_X_LEFT} y2={CORNER_LINE_TOP_Y} />
                <path d={`M${CORNER_X_LEFT},${CORNER_LINE_TOP_Y} A${ARC_RADIUS},${ARC_RADIUS} 0 0,1 ${CORNER_X_RIGHT},${CORNER_LINE_TOP_Y}`} />
                <line x1={CORNER_X_RIGHT} y1={H} x2={CORNER_X_RIGHT} y2={CORNER_LINE_TOP_Y} />

                {/* Restricted area arc */}
                <path d={`M${CX - 40},${HOOP_Y} A40,40 0 0,1 ${CX + 40},${HOOP_Y}`} />

                {/* Backboard */}
                <line x1={CX - BACKBOARD_HALF} y1={BACKBOARD_Y} x2={CX + BACKBOARD_HALF} y2={BACKBOARD_Y} strokeWidth={3} strokeOpacity={0.4} />
            </g>

            {/* ── Hoop ───────────────────────────────────────── */}
            <circle
                cx={CX}
                cy={HOOP_Y}
                r={HOOP_RADIUS}
                fill="none"
                stroke="currentColor"
                strokeOpacity={0.5}
                strokeWidth={2.5}
                style={{ pointerEvents: 'none' }}
            />
        </svg>
    );
}
