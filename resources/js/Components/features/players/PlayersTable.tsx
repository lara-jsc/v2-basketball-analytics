import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { type PlayerStat, type PlayerWithStats, type Team } from '@/types';
import { Link, router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, ClipboardList, Pencil, Power, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DeletePlayerDialog } from './DeletePlayerDialog';
import { PlayerFormSheet } from './PlayerFormSheet';

interface PlayersTableProps {
    players: PlayerWithStats[];
    team: Team;
}

/**
 * Horizontally-scrollable stats table for all players on a team.
 *
 * UX improvements:
 *   - Defaults to "key stats" view (core columns only) to avoid overwhelming users
 *   - "Show all stats" toggle reveals all 36 columns
 *   - Dark striped rows (even rows tinted)
 *   - Monospace font on all stat values
 *   - Amber highlight on +/- column — null displays as "—" per spec
 *
 * Column notes:
 *   OR  → offensive_rebounds in DB (MySQL reserved word workaround)
 *   TO  → to_per_game in DB         (MySQL reserved word workaround)
 */
export function PlayersTable({ players, team }: PlayersTableProps) {
    const [editPlayer, setEditPlayer]   = useState<PlayerWithStats | null>(null);
    const [deletePlayer, setDeletePlayer] = useState<PlayerWithStats | null>(null);
    const [showAll, setShowAll]         = useState(false);

    /** Keep the edit drawer avatar in sync when `players` updates (e.g. picture upload). Merge path only so unsaved form edits are preserved. */
    useEffect(() => {
        setEditPlayer((prev) => {
            if (prev === null) return prev;
            const next = players.find((p) => p.id === prev.id);
            if (!next || next.profile_picture_path === prev.profile_picture_path) return prev;
            return { ...prev, profile_picture_path: next.profile_picture_path };
        });
    }, [players]);

    function handleToggleActive(player: PlayerWithStats) {
        router.patch(route('players.toggleActive', { id: player.id }));
    }

    if (players.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card py-12 text-center gap-3">
                <p className="font-display text-sm font-bold tracking-wide text-muted-foreground uppercase">No players yet</p>
                <p className="text-xs text-muted-foreground font-ui">Upload a CSV file or add players manually using the button above.</p>
            </div>
        );
    }

    return (
        <>
            {/* Column toggle */}
            <div className="mb-2 flex items-center justify-between">
                <p className="text-xs text-muted-foreground">
                    {players.length} player{players.length !== 1 ? 's' : ''}
                    {!showAll && ' · showing key stats'}
                </p>
                <button
                    onClick={() => setShowAll((v) => !v)}
                    className="flex items-center gap-1.5 text-xs font-ui font-semibold tracking-wide text-muted-foreground hover:text-foreground transition-colors"
                >
                    {showAll ? (
                        <><ChevronUp size={13} /> Show fewer columns</>
                    ) : (
                        <><ChevronDown size={13} /> Show all {EXTRA_COLS.length + KEY_COLS.length} columns</>
                    )}
                </button>
            </div>

            <div className="overflow-x-auto rounded-xl border border-border bg-card">
                <Table>
                    <TableHeader>
                        <TableRow className="bg-muted/60 hover:bg-muted/60">
                            {/* Actions */}
                            <TableHead className="sticky left-0 z-10 bg-muted/60 w-24 whitespace-nowrap font-ui font-semibold tracking-wide text-xs uppercase">
                                Actions
                            </TableHead>
                            {/* Jersey */}
                            <TableHead className="sticky left-24 z-10 bg-muted/60 whitespace-nowrap w-10 font-ui font-semibold tracking-wide text-xs uppercase">
                                #
                            </TableHead>
                            {/* Name */}
                            <TableHead className="sticky left-[7rem] z-10 bg-muted/60 whitespace-nowrap min-w-[140px] font-ui font-semibold tracking-wide text-xs uppercase">
                                Name
                            </TableHead>
                            {/* Key stat columns */}
                            {KEY_COLS.map((col) => (
                                <TableHead
                                    key={col.key}
                                    className={[
                                        'whitespace-nowrap font-ui font-semibold tracking-wide text-xs uppercase',
                                        col.key === 'plus_minus'
                                            ? 'text-accent bg-accent/8 border-b border-accent/20'
                                            : '',
                                    ].join(' ')}
                                >
                                    {col.label}
                                </TableHead>
                            ))}
                            {/* Extra columns — only when showAll */}
                            {showAll && EXTRA_COLS.map((col) => (
                                <TableHead key={col.key} className="whitespace-nowrap font-ui font-semibold tracking-wide text-xs uppercase">
                                    {col.label}
                                </TableHead>
                            ))}
                        </TableRow>
                    </TableHeader>

                    <TableBody>
                        {players.map((player, i) => {
                            const stat: PlayerStat | null = player.stats[0] ?? null;
                            const rowBg = i % 2 === 0 ? '' : 'bg-primary/[0.03]';

                            return (
                                <TableRow
                                    key={player.id}
                                    className={`${rowBg} hover:bg-primary/5 ${!player.is_active ? 'opacity-50' : ''}`}
                                >
                                    {/* Actions */}
                                    <TableCell className="sticky left-0 z-10 bg-card">
                                        <div className="flex items-center gap-1">
                                            <Link
                                                href={route('player-histories.index', player.id)}
                                                title="Game history"
                                                className="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            >
                                                <ClipboardList size={12} />
                                            </Link>
                                            <ActionBtn
                                                title="Edit player"
                                                onClick={() => setEditPlayer(player)}
                                            >
                                                <Pencil size={12} />
                                            </ActionBtn>
                                            <ActionBtn
                                                title={player.is_active ? 'Deactivate' : 'Activate'}
                                                className={player.is_active ? '' : 'text-accent'}
                                                onClick={() => handleToggleActive(player)}
                                            >
                                                <Power size={12} />
                                            </ActionBtn>
                                            <ActionBtn
                                                title="Remove player"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() => setDeletePlayer(player)}
                                            >
                                                <Trash2 size={12} />
                                            </ActionBtn>
                                        </div>
                                    </TableCell>

                                    {/* Jersey */}
                                    <TableCell className="sticky left-24 z-10 bg-card font-mono font-medium">
                                        {player.jersey_number}
                                    </TableCell>

                                    {/* Name + avatar */}
                                    <TableCell className="sticky left-[7rem] z-10 bg-card whitespace-nowrap">
                                        <div className="flex items-center gap-2">
                                            {player.profile_picture_path ? (
                                                <img
                                                    src={`/storage/${player.profile_picture_path}`}
                                                    alt=""
                                                    className="h-6 w-6 rounded-full object-cover shrink-0"
                                                />
                                            ) : (
                                                <div className="h-6 w-6 rounded-full bg-primary/10 flex items-center justify-center shrink-0 text-[10px] font-bold text-primary">
                                                    {player.first_name[0]}{player.last_name[0]}
                                                </div>
                                            )}
                                            <span className="font-medium text-sm">
                                                {player.first_name} {player.last_name}
                                            </span>
                                            <span className="text-[10px] font-ui text-muted-foreground">
                                                {player.role ?? ''}
                                            </span>
                                        </div>
                                    </TableCell>

                                    {/* Key stat cells */}
                                    {KEY_COLS.map((col) => (
                                        <TableCell
                                            key={col.key}
                                            className={[
                                                'whitespace-nowrap font-mono text-sm',
                                                col.key === 'plus_minus'
                                                    ? 'font-bold text-accent bg-accent/5'
                                                    : col.key === 'is_active'
                                                    ? ''
                                                    : 'text-foreground/80',
                                            ].join(' ')}
                                        >
                                            {col.render(player, stat)}
                                        </TableCell>
                                    ))}

                                    {/* Extra stat cells */}
                                    {showAll && EXTRA_COLS.map((col) => (
                                        <TableCell key={col.key} className="whitespace-nowrap font-mono text-sm text-foreground/80">
                                            {col.render(player, stat)}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </div>

            {/* Edit drawer */}
            {editPlayer && (
                <PlayerFormSheet
                    open={!!editPlayer}
                    onOpenChange={(open) => !open && setEditPlayer(null)}
                    team={team}
                    player={editPlayer}
                />
            )}

            {/* Delete dialog */}
            {deletePlayer && (
                <DeletePlayerDialog
                    open={!!deletePlayer}
                    onOpenChange={(open) => !open && setDeletePlayer(null)}
                    player={deletePlayer}
                />
            )}
        </>
    );
}

// ── Internal helpers ──────────────────────────────────────────────────────────

function ActionBtn({
    children,
    title,
    className = '',
    onClick,
}: {
    children: React.ReactNode;
    title: string;
    className?: string;
    onClick: () => void;
}) {
    return (
        <button
            title={title}
            onClick={onClick}
            className={`flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground ${className}`}
        >
            {children}
        </button>
    );
}

function fmt(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return value.toString();
}

function formatPlusMinus(value: number | null): string {
    if (value === null) return '—';
    return value >= 0 ? `+${value.toFixed(1)}` : `${value.toFixed(1)}`;
}

// ── Column definitions ────────────────────────────────────────────────────────

type ColDef = {
    key: string;
    label: string;
    render: (player: PlayerWithStats, stat: PlayerStat | null) => React.ReactNode;
};

/** Always visible — the columns most useful at a glance */
const KEY_COLS: ColDef[] = [
    { key: 'plus_minus', label: '+/-',    render: (_, s) => formatPlusMinus(s?.plus_minus ?? null) },
    { key: 'pts',        label: 'PTS',    render: (_, s) => fmt(s?.pts) },
    { key: 'reb',        label: 'REB',    render: (_, s) => fmt(s?.reb) },
    { key: 'ast',        label: 'AST',    render: (_, s) => fmt(s?.ast) },
    { key: 'blk',        label: 'BLK',    render: (_, s) => fmt(s?.blk) },
    { key: 'stl',        label: 'STL',    render: (_, s) => fmt(s?.stl) },
    { key: 'min',        label: 'MIN',    render: (_, s) => fmt(s?.min) },
    { key: 'fg_pct',     label: 'FG%',    render: (_, s) => fmt(s?.fg_pct) },
    { key: 'gp',         label: 'GP',     render: (_, s) => fmt(s?.gp) },
];

/** Hidden by default — revealed via "Show all columns" toggle */
const EXTRA_COLS: ColDef[] = [
    { key: 'fg',              label: 'FG',      render: (_, s) => s?.fg ?? '—' },
    { key: 'three_p_pct',     label: '3P%',     render: (_, s) => fmt(s?.three_p_pct) },
    { key: 'three_pt',        label: '3PT',     render: (_, s) => s?.three_pt ?? '—' },
    { key: 'ft_pct',          label: 'FT%',     render: (_, s) => fmt(s?.ft_pct) },
    { key: 'ft',              label: 'FT',      render: (_, s) => s?.ft ?? '—' },
    { key: 'sc_eff',          label: 'SC-EFF',  render: (_, s) => fmt(s?.sc_eff) },
    { key: 'sh_eff',          label: 'SH-EFF',  render: (_, s) => fmt(s?.sh_eff) },
    { key: 'dr',              label: 'DR',      render: (_, s) => fmt(s?.dr) },
    { key: 'offensive_reb',   label: 'OR',      render: (_, s) => fmt(s?.offensive_rebounds) },
    { key: 'to_per_game',     label: 'TO',      render: (_, s) => fmt(s?.to_per_game) },
    { key: 'ast_to',          label: 'AST/TO',  render: (_, s) => fmt(s?.ast_to) },
    { key: 'stl_to',          label: 'STL/TO',  render: (_, s) => fmt(s?.stl_to) },
    { key: 'pf',              label: 'PF',      render: (_, s) => fmt(s?.pf) },
    { key: 'flag',            label: 'FLAG',    render: (_, s) => s?.flag ?? '—' },
    { key: 'tech',            label: 'TECH',    render: (_, s) => s?.tech ?? '—' },
    { key: 'eject',           label: 'EJECT',   render: (_, s) => s?.eject ?? '—' },
    { key: 'dq',              label: 'DQ',      render: (_, s) => s?.dq ?? '—' },
    { key: 'gs',              label: 'GS',      render: (_, s) => fmt(s?.gs) },
    { key: 'dd2',             label: 'DD2',     render: (_, s) => s?.dd2 ?? '—' },
    { key: 'td3',             label: 'TD3',     render: (_, s) => s?.td3 ?? '—' },
    { key: 'pc',              label: 'PC',      render: (_, s) => s?.pc ?? '—' },
    { key: 'sd',              label: 'SD',      render: (_, s) => s?.sd ?? '—' },
];
