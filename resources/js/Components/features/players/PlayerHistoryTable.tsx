import type { PlayerHistory, PlayerHistoryFilters } from '@/types/PlayerHistory.types';
import type { Team } from '@/types';
import { router } from '@inertiajs/react';
import { AlertCircle, CalendarDays, ClipboardList, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { PlayerHistorySheet } from './PlayerHistorySheet';

interface PlayerHistoryTableProps {
    playerId: number;
    playerName: string;
    playingTeam: Pick<Team, 'id' | 'code' | 'name'>;
    histories: PlayerHistory[];
    teams: Pick<Team, 'id' | 'code' | 'name'>[];
    filters: PlayerHistoryFilters;
    error?: string;
}

function fmt(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return String(value);
}

function fmtDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
}

function fmtShortDate(iso: string): string {
    return new Date(iso).toLocaleDateString('en-US', {
        month: 'short', day: 'numeric', year: '2-digit',
    });
}

export function PlayerHistoryTable({
    playerId,
    playerName,
    playingTeam,
    histories,
    teams,
    filters,
    error,
}: PlayerHistoryTableProps) {
    const [deleting, setDeleting]           = useState<number | null>(null);
    const [sheetOpen, setSheetOpen]         = useState(false);
    const [editingHistory, setEditingHistory] = useState<PlayerHistory | undefined>(undefined);
    const [selectedId, setSelectedId]       = useState<number | null>(histories[0]?.id ?? null);

    function openAdd() {
        setEditingHistory(undefined);
        setSheetOpen(true);
    }

    function openEdit(history: PlayerHistory) {
        setEditingHistory(history);
        setSheetOpen(true);
    }

    function applyFilter(key: string, value: string) {
        router.get(
            route('player-histories.index', playerId),
            { ...filters, [key]: value || undefined },
            { preserveState: true, replace: true },
        );
    }

    function handleDelete(history: PlayerHistory) {
        if (
            !window.confirm(
                'Removing this game will recalculate this player\'s stats. Continue?',
            )
        ) return;
        setDeleting(history.id);
        router.delete(route('player-histories.destroy', history.id), {
            onFinish: () => setDeleting(null),
        });
    }

    if (error) {
        return (
            <div className="flex flex-col items-center justify-center rounded-xl border border-destructive/30 bg-destructive/5 py-12 gap-3 text-center">
                <AlertCircle size={20} className="text-destructive" />
                <p className="text-sm font-medium text-destructive">{error}</p>
                <button
                    onClick={() => router.reload()}
                    className="flex items-center gap-1.5 text-xs font-ui font-semibold text-muted-foreground hover:text-foreground transition-colors"
                >
                    <RefreshCw size={12} />
                    Retry
                </button>
            </div>
        );
    }

    const selected = histories.find((h) => h.id === selectedId) ?? null;

    const fgStr = selected && selected.field_goals_made !== null && selected.field_goals_attempted !== null
        ? `${selected.field_goals_made}-${selected.field_goals_attempted}`
        : '—';
    const tptStr = selected && selected.three_pointers_made !== null && selected.three_pointers_attempted !== null
        ? `${selected.three_pointers_made}-${selected.three_pointers_attempted}`
        : '—';
    const ftStr = selected && selected.free_throws_made !== null && selected.free_throws_attempted !== null
        ? `${selected.free_throws_made}-${selected.free_throws_attempted}`
        : '—';

    const fgPct = selected && selected.field_goals_made !== null && selected.field_goals_attempted !== null && selected.field_goals_attempted > 0
        ? ((selected.field_goals_made / selected.field_goals_attempted) * 100).toFixed(1)
        : '—';

    return (
        <div className="space-y-4">
            {/* ── Toolbar ─────────────────────────────────────────────── */}
            <div className="flex flex-wrap items-center gap-3">
                <div className="flex items-center gap-1.5">
                    <CalendarDays size={13} className="text-muted-foreground shrink-0" />
                    <input
                        type="date"
                        defaultValue={filters.from ?? ''}
                        onChange={(e) => applyFilter('from', e.target.value)}
                        className="h-8 rounded-md border border-border bg-card px-2 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        title="From date"
                    />
                    <span className="text-xs text-muted-foreground">–</span>
                    <input
                        type="date"
                        defaultValue={filters.to ?? ''}
                        onChange={(e) => applyFilter('to', e.target.value)}
                        className="h-8 rounded-md border border-border bg-card px-2 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                        title="To date"
                    />
                </div>

                <select
                    defaultValue={filters.opponent_team_id ?? ''}
                    onChange={(e) => applyFilter('opponent_team_id', e.target.value)}
                    className="h-8 rounded-md border border-border bg-card px-2 text-xs text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
                >
                    <option value="">All opponents</option>
                    {teams.map((t) => (
                        <option key={t.id} value={t.id}>{t.code} — {t.name}</option>
                    ))}
                </select>

                <div className="flex-1" />

                <button
                    type="button"
                    onClick={openAdd}
                    className="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-ui font-semibold tracking-wide text-primary-foreground transition-opacity hover:opacity-90"
                >
                    <Plus size={13} />
                    Add Game
                </button>
            </div>

            {/* ── Empty state ─────────────────────────────────────────── */}
            {histories.length === 0 && (
                <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card py-14 gap-3 text-center">
                    <ClipboardList size={24} className="text-muted-foreground/50" />
                    <p className="font-display text-sm font-bold tracking-wide text-muted-foreground">
                        No game history recorded yet
                    </p>
                    <p className="text-xs text-muted-foreground max-w-xs">
                        Import a CSV file or add a game manually using the button above.
                    </p>
                </div>
            )}

            {/* ── Two-column panel ────────────────────────────────────── */}
            {histories.length > 0 && (
                <div className="flex rounded-xl border border-border bg-card overflow-hidden" style={{ minHeight: '480px' }}>

                    {/* Left panel: game list */}
                    <div className="w-56 border-r border-border flex flex-col shrink-0">
                        <div className="px-4 py-3 border-b border-border">
                            <p className="text-[10px] font-ui font-bold tracking-widest text-muted-foreground uppercase">
                                Games · {histories.length}
                            </p>
                        </div>

                        <div className="flex-1 overflow-y-auto">
                            {histories.map((h) => {
                                const playCode = h.playing_team?.code ?? '???';
                                const oppCode  = h.opponent_team?.code ?? '???';
                                const isSelected = selectedId === h.id;
                                return (
                                    <button
                                        key={h.id}
                                        onClick={() => setSelectedId(h.id)}
                                        className={[
                                            'w-full flex flex-col gap-1.5 px-4 py-3 text-left transition-colors',
                                            isSelected
                                                ? 'bg-primary/15 border-l-2 border-primary'
                                                : 'border-l-2 border-transparent hover:bg-muted/30',
                                        ].join(' ')}
                                    >
                                        {/* Team badges */}
                                        <div className="flex items-center gap-1.5">
                                            <TeamBadge code={playCode} highlight />
                                            <span className="text-[10px] font-ui font-semibold text-muted-foreground">vs</span>
                                            <TeamBadge code={oppCode} />
                                        </div>
                                        {/* Date + GS */}
                                        <div className="flex items-center justify-between w-full">
                                            <span className="text-[10px] font-ui text-muted-foreground">{fmtShortDate(h.game_date)}</span>
                                            {h.is_started && (
                                                <span className="text-[9px] font-ui font-bold tracking-widest text-accent uppercase">GS</span>
                                            )}
                                        </div>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* Right panel: game detail */}
                    {selected ? (
                        <div className="flex-1 flex flex-col p-6 gap-4 overflow-y-auto">

                            {/* Game header */}
                            <div className="flex items-start justify-between">
                                <div className="flex items-center gap-4">
                                    {/* Opponent badge */}
                                    <div className="h-16 w-16 rounded-full bg-muted/40 border-2 border-border flex items-center justify-center text-lg font-bold text-foreground shrink-0">
                                        {selected.opponent_team?.code?.slice(0, 2) ?? '??'}
                                    </div>
                                    <div>
                                        <p className="text-[10px] font-ui font-semibold tracking-widest text-muted-foreground uppercase mb-0.5">
                                            Opponent
                                        </p>
                                        <h2 className="font-display text-2xl font-bold tracking-wide text-foreground uppercase">
                                            {selected.opponent_team
                                                ? `${selected.opponent_team.code} — ${selected.opponent_team.name}`
                                                : '—'}
                                        </h2>
                                        <p className="text-sm text-muted-foreground font-ui mt-0.5">
                                            {fmtDate(selected.game_date)}
                                            {selected.position_played ? ` · ${selected.position_played}` : ''}
                                            {selected.is_started ? ' · Started' : ''}
                                        </p>
                                    </div>
                                </div>

                                {/* Points badge */}
                                <div className="rounded-lg border border-accent/30 bg-accent/10 px-4 py-2 text-center shrink-0">
                                    <p className="font-display text-2xl font-bold text-accent leading-none">
                                        {fmt(selected.points)}
                                    </p>
                                    <p className="text-[10px] font-ui text-muted-foreground mt-1">PTS</p>
                                </div>
                            </div>

                            {/* Stat panels — 3 per row */}
                            <div className="grid grid-cols-3 gap-3">
                                <StatPanel label="Scoring">
                                    <StatItem label="FG"  value={fgStr} />
                                    <StatItem label="FG%" value={fgPct} />
                                    <StatItem label="3PT" value={tptStr} />
                                    <StatItem label="FT"  value={ftStr} />
                                </StatPanel>

                                <StatPanel label="Playmaking">
                                    <StatItem label="AST" value={fmt(selected.assists)} />
                                    <StatItem label="TO"  value={fmt(selected.turnovers)} />
                                </StatPanel>

                                <StatPanel label="Rebounding">
                                    <StatItem label="REB" value={fmt(selected.rebounds)} />
                                    <StatItem label="OR"  value={fmt(selected.offensive_rebounds)} />
                                    <StatItem label="DR"  value={fmt(selected.defensive_rebounds)} />
                                </StatPanel>

                                <StatPanel label="Defense">
                                    <StatItem label="STL" value={fmt(selected.steals)} />
                                    <StatItem label="BLK" value={fmt(selected.blocks)} />
                                </StatPanel>

                                <StatPanel label="Playing Time">
                                    <StatItem label="MIN" value={fmt(selected.minutes_played)} />
                                    <StatItem label="PF"  value={fmt(selected.personal_fouls)} />
                                </StatPanel>

                                <StatPanel label="Discipline">
                                    <StatItem label="FLAG"  value={fmt(selected.flagrant_fouls)} />
                                    <StatItem label="TECH"  value={fmt(selected.technical_fouls)} />
                                    <StatItem label="EJECT" value={fmt(selected.ejections)} />
                                    <StatItem label="DQ"    value={fmt(selected.disqualifications)} />
                                </StatPanel>
                            </div>

                            {/* Notes */}
                            {selected.notes && (
                                <div className="rounded-lg border border-border bg-muted/20 p-3">
                                    <p className="text-[10px] font-ui font-semibold tracking-widest text-muted-foreground uppercase mb-2">
                                        Notes
                                    </p>
                                    <p className="text-sm text-foreground/80">{selected.notes}</p>
                                </div>
                            )}

                            {/* Actions */}
                            <div className="flex items-center gap-2 pt-3 border-t border-border mt-auto">
                                <ActionBtn title="Edit game" onClick={() => openEdit(selected)}>
                                    <Pencil size={14} />
                                </ActionBtn>
                                <ActionBtn
                                    title="Remove game"
                                    className="text-destructive hover:text-destructive"
                                    disabled={deleting === selected.id}
                                    onClick={() => handleDelete(selected)}
                                >
                                    <Trash2 size={14} />
                                </ActionBtn>
                            </div>
                        </div>
                    ) : (
                        <div className="flex-1 flex items-center justify-center text-sm text-muted-foreground">
                            Select a game
                        </div>
                    )}
                </div>
            )}

            <PlayerHistorySheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                playerId={playerId}
                playerName={playerName}
                playingTeam={playingTeam}
                teams={teams}
                history={editingHistory}
            />
        </div>
    );
}

// ── Internal helpers ──────────────────────────────────────────────────────────

function TeamBadge({ code, highlight = false }: { code: string; highlight?: boolean }) {
    return (
        <div
            className={[
                'h-7 w-7 rounded-full flex items-center justify-center text-[9px] font-bold font-ui shrink-0',
                highlight
                    ? 'bg-primary/20 border border-primary/40 text-primary'
                    : 'bg-muted/40 border border-border text-muted-foreground',
            ].join(' ')}
        >
            {code.slice(0, 3)}
        </div>
    );
}

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
    disabled = false,
    onClick,
}: {
    children: React.ReactNode;
    title: string;
    className?: string;
    disabled?: boolean;
    onClick: () => void;
}) {
    return (
        <button
            title={title}
            disabled={disabled}
            onClick={onClick}
            className={`flex h-9 w-9 items-center justify-center rounded-md border border-border text-muted-foreground transition-colors hover:bg-muted hover:text-foreground disabled:opacity-40 ${className}`}
        >
            {children}
        </button>
    );
}
