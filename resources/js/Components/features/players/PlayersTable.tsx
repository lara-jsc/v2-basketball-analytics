import { type PlayerStat, type PlayerWithStats, type Team } from '@/types';
import { Link, router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, ClipboardList, Info, Pencil, Power, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { DeletePlayerDialog } from './DeletePlayerDialog';
import { PlayerFormSheet } from './PlayerFormSheet';
import { PlusMinusBreakdownModal } from './PlusMinusBreakdownModal';

interface PlayersTableProps {
    players: PlayerWithStats[];
    team: Team;
}

export function PlayersTable({ players, team }: PlayersTableProps) {
    const [editPlayer, setEditPlayer]         = useState<PlayerWithStats | null>(null);
    const [deletePlayer, setDeletePlayer]     = useState<PlayerWithStats | null>(null);
    const [selectedId, setSelectedId]         = useState<number | null>(players[0]?.id ?? null);
    const [search, setSearch]                 = useState('');
    const [showAll, setShowAll]               = useState(false);
    const [showBreakdown, setShowBreakdown]   = useState(false);
    const [breakdownPlayerId, setBreakdownPlayerId] = useState<number | null>(null);

    function openBreakdown(playerId: number) {
        setBreakdownPlayerId(playerId);
        setShowBreakdown(true);
    }

    /** Keep the edit drawer avatar in sync when `players` updates (e.g. picture upload). */
    useEffect(() => {
        setEditPlayer((prev) => {
            if (prev === null) return prev;
            const next = players.find((p) => p.id === prev.id);
            if (!next || next.profile_picture_path === prev.profile_picture_path) return prev;
            return { ...prev, profile_picture_path: next.profile_picture_path };
        });
    }, [players]);

    /** If selected player was removed, fall back to first player. */
    useEffect(() => {
        if (selectedId !== null && !players.find((p) => p.id === selectedId)) {
            setSelectedId(players[0]?.id ?? null);
        }
    }, [players, selectedId]);

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

    const filteredPlayers = players.filter((p) =>
        `${p.first_name} ${p.last_name}`.toLowerCase().includes(search.toLowerCase()),
    );

    const selectedPlayer = players.find((p) => p.id === selectedId) ?? null;
    const stat: PlayerStat | null = selectedPlayer?.stats[0] ?? null;

    return (
        <>
            <div className="flex rounded-xl border border-border bg-card overflow-hidden" style={{ minHeight: '480px' }}>

                {/* ── Left panel: player list ─────────────────────────────── */}
                <div className="w-56 border-r border-border flex flex-col shrink-0">
                    {/* Header */}
                    <div className="px-4 py-3 border-b border-border">
                        <p className="text-[10px] font-ui font-bold tracking-widest text-muted-foreground uppercase">
                            Players
                        </p>
                    </div>

                    {/* Search */}
                    <div className="px-3 py-2 border-b border-border">
                        <input
                            type="text"
                            placeholder="Search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="w-full bg-transparent text-sm text-foreground placeholder:text-muted-foreground/50 outline-none"
                        />
                    </div>

                    {/* List */}
                    <div className="flex-1 overflow-y-auto">
                        {filteredPlayers.map((player) => (
                            <button
                                key={player.id}
                                onClick={() => { setSelectedId(player.id); setShowAll(false); }}
                                className={[
                                    'w-full flex items-center justify-between px-4 py-2.5 text-left transition-colors',
                                    selectedId === player.id
                                        ? 'bg-primary/15 border-l-2 border-primary text-foreground'
                                        : 'border-l-2 border-transparent hover:bg-muted/30 text-muted-foreground hover:text-foreground',
                                    !player.is_active ? 'opacity-50' : '',
                                ].join(' ')}
                            >
                                <span className="text-sm font-medium truncate">
                                    {player.first_name} {player.last_name}
                                </span>
                                <div className="flex items-center gap-1 shrink-0 ml-2">
                                    <span className="text-[10px] font-ui font-bold text-muted-foreground">{team.code}</span>
                                    <span className="text-[10px] font-mono text-muted-foreground">#{player.jersey_number}</span>
                                </div>
                            </button>
                        ))}
                        {filteredPlayers.length === 0 && (
                            <p className="text-xs text-muted-foreground text-center py-6">No results</p>
                        )}
                    </div>
                </div>

                {/* ── Right panel: player detail ──────────────────────────── */}
                {selectedPlayer ? (
                    <div className="flex-1 flex flex-col p-6 gap-4 overflow-y-auto">

                        {/* Player header */}
                        <div className="flex items-start justify-between">
                            <div className="flex items-center gap-4">
                                {selectedPlayer.profile_picture_path ? (
                                    <img
                                        src={`/storage/${selectedPlayer.profile_picture_path}`}
                                        alt=""
                                        className="h-16 w-16 rounded-full object-cover border-2 border-border shrink-0"
                                    />
                                ) : (
                                    <div className="h-16 w-16 rounded-full bg-primary/10 border-2 border-primary/20 flex items-center justify-center text-xl font-bold text-primary shrink-0">
                                        {selectedPlayer.first_name[0]}{selectedPlayer.last_name[0]}
                                    </div>
                                )}
                                <div>
                                    <h2 className="font-display text-2xl font-bold tracking-wide text-foreground uppercase">
                                        {selectedPlayer.first_name} {selectedPlayer.last_name}
                                    </h2>
                                    <p className="text-sm text-muted-foreground font-ui mt-0.5">
                                        #{selectedPlayer.jersey_number}
                                        {selectedPlayer.role ? ` · ${selectedPlayer.role}` : ''}
                                    </p>
                                </div>
                            </div>

                            {/* Plus-minus badge — click to see computation breakdown */}
                            <button
                                type="button"
                                title="Click to see how this rating is calculated"
                                onClick={() => openBreakdown(selectedPlayer.id)}
                                className="rounded-lg border border-accent/30 bg-accent/10 px-4 py-2 text-center shrink-0 cursor-pointer hover:bg-accent/20 transition-colors group"
                            >
                                <p className="font-display text-2xl font-bold text-accent leading-none">
                                    {formatPlusMinus(stat?.plus_minus ?? null)}
                                </p>
                                <p className="text-[10px] font-ui text-muted-foreground mt-1 flex items-center justify-center gap-1">
                                    +/- Rating
                                    <Info size={9} className="opacity-50 group-hover:opacity-100 transition-opacity" />
                                </p>
                            </button>
                        </div>

                        {/* Stat panels */}
                        <div className="grid grid-cols-2 gap-3">
                            <StatPanel label="Season Performance Overall">
                                <StatItem label="PTS"  value={fmt(stat?.pts)} />
                                <StatItem label="FG%"  value={fmt(stat?.fg_pct)} />
                                <StatItem label="GP"   value={fmt(stat?.gp)} />
                            </StatPanel>

                            <StatPanel label="Playmaking">
                                <StatItem label="AST"    value={fmt(stat?.ast)} />
                                <StatItem label="AST/TO" value={fmt(stat?.ast_to)} />
                            </StatPanel>

                            <StatPanel label="Rebounding">
                                <StatItem label="REB" value={fmt(stat?.reb)} />
                                <StatItem label="DR"  value={fmt(stat?.dr)} />
                                <StatItem label="OR"  value={fmt(stat?.offensive_rebounds)} />
                            </StatPanel>

                            <StatPanel label="Defensive Metrics">
                                <StatItem label="STL" value={fmt(stat?.stl)} />
                                <StatItem label="BLK" value={fmt(stat?.blk)} />
                            </StatPanel>
                        </div>

                        <StatPanel label="Playing Time">
                            <StatItem label="MIN" value={fmt(stat?.min)} />
                            <StatItem label="GS"  value={fmt(stat?.gs)} />
                        </StatPanel>

                        {/* Show all stats toggle */}
                        <button
                            onClick={() => setShowAll((v) => !v)}
                            className="flex items-center gap-1.5 text-xs font-ui font-semibold tracking-wide text-muted-foreground hover:text-foreground transition-colors self-start"
                        >
                            {showAll ? <ChevronUp size={13} /> : <ChevronDown size={13} />}
                            {showAll ? 'Show fewer stats' : 'Show all stats'}
                        </button>

                        {/* Expanded stat panels */}
                        {showAll && (
                            <div className="flex flex-col gap-3">
                                <div className="grid grid-cols-2 gap-3">
                                    <StatPanel label="Shooting">
                                        <StatItem label="FG"      value={stat?.fg ?? '—'} />
                                        <StatItem label="3P%"     value={fmt(stat?.three_p_pct)} />
                                        <StatItem label="3PT"     value={stat?.three_pt ?? '—'} />
                                    </StatPanel>

                                    <StatPanel label="Free Throws & Efficiency">
                                        <StatItem label="FT%"    value={fmt(stat?.ft_pct)} />
                                        <StatItem label="FT"     value={stat?.ft ?? '—'} />
                                        <StatItem label="SC-EFF" value={fmt(stat?.sc_eff)} />
                                        <StatItem label="SH-EFF" value={fmt(stat?.sh_eff)} />
                                    </StatPanel>

                                    <StatPanel label="Turnovers & Fouls">
                                        <StatItem label="TO"     value={fmt(stat?.to_per_game)} />
                                        <StatItem label="STL/TO" value={fmt(stat?.stl_to)} />
                                        <StatItem label="PF"     value={fmt(stat?.pf)} />
                                    </StatPanel>

                                    <StatPanel label="Discipline">
                                        <StatItem label="FLAG"  value={stat?.flag !== undefined && stat?.flag !== null ? String(stat.flag) : '—'} />
                                        <StatItem label="TECH"  value={stat?.tech !== undefined && stat?.tech !== null ? String(stat.tech) : '—'} />
                                        <StatItem label="EJECT" value={stat?.eject !== undefined && stat?.eject !== null ? String(stat.eject) : '—'} />
                                        <StatItem label="DQ"    value={stat?.dq !== undefined && stat?.dq !== null ? String(stat.dq) : '—'} />
                                    </StatPanel>
                                </div>

                                <StatPanel label="Milestones & Position">
                                    <StatItem label="DD2" value={stat?.dd2 !== undefined && stat?.dd2 !== null ? String(stat.dd2) : '—'} />
                                    <StatItem label="TD3" value={stat?.td3 !== undefined && stat?.td3 !== null ? String(stat.td3) : '—'} />
                                    <StatItem label="PC"  value={stat?.pc ?? '—'} />
                                    <StatItem label="SD"  value={stat?.sd ?? '—'} />
                                </StatPanel>
                            </div>
                        )}

                        {/* Actions */}
                        <div className="flex items-center gap-2 pt-3 border-t border-border mt-auto">
                            <Link
                                href={route('player-histories.index', selectedPlayer.id)}
                                title="Game history"
                                className="flex h-9 w-9 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                                <ClipboardList size={14} />
                            </Link>
                            <ActionBtn title="Edit player" onClick={() => setEditPlayer(selectedPlayer)}>
                                <Pencil size={14} />
                            </ActionBtn>
                            <ActionBtn
                                title={selectedPlayer.is_active ? 'Deactivate' : 'Activate'}
                                className={selectedPlayer.is_active ? '' : 'text-accent'}
                                onClick={() => handleToggleActive(selectedPlayer)}
                            >
                                <Power size={14} />
                            </ActionBtn>
                            <ActionBtn
                                title="Remove player"
                                className="text-destructive hover:text-destructive"
                                onClick={() => setDeletePlayer(selectedPlayer)}
                            >
                                <Trash2 size={14} />
                            </ActionBtn>
                        </div>
                    </div>
                ) : (
                    <div className="flex-1 flex items-center justify-center text-sm text-muted-foreground">
                        Select a player
                    </div>
                )}
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

            {/* Plus/minus breakdown modal */}
            {breakdownPlayerId !== null && (
                <PlusMinusBreakdownModal
                    open={showBreakdown}
                    onOpenChange={(open) => {
                        setShowBreakdown(open);
                        if (! open) setBreakdownPlayerId(null);
                    }}
                    playerId={breakdownPlayerId}
                />
            )}
        </>
    );
}

// ── Internal helpers ──────────────────────────────────────────────────────────

function StatPanel({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="rounded-lg border border-border bg-muted/20 p-3">
            <p className="text-[10px] font-ui font-semibold tracking-widest text-muted-foreground uppercase mb-3">
                {label}
            </p>
            <div className="flex items-end gap-6">
                {children}
            </div>
        </div>
    );
}

function StatItem({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex flex-col items-start gap-0.5">
            <span className="text-[10px] font-ui font-semibold tracking-widest text-muted-foreground uppercase">
                {label}
            </span>
            <span className="font-mono text-lg font-bold text-foreground leading-none">
                {value}
            </span>
        </div>
    );
}

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
            className={`flex h-9 w-9 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground ${className}`}
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
