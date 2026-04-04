import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import type { PlayerHistory, PlayerHistoryFilters } from '@/types/PlayerHistory.types';
import type { Team } from '@/types';
import { router } from '@inertiajs/react';
import { AlertCircle, CalendarDays, ClipboardList, Pencil, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { PlayerHistorySheet } from './PlayerHistorySheet';

interface PlayerHistoryTableProps {
    playerId: number;
    playerName: string;
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

/**
 * Game history table for a single player.
 *
 * Columns: date, playing team, opponent, position, MIN, PTS, REB, AST, STL, BLK, TO, FG, 3PT, FT
 * Sorted by game_date descending (server-side).
 * Includes filter bar (date range + team selectors), empty state, loading skeleton, error state.
 */
export function PlayerHistoryTable({
    playerId,
    playerName,
    histories,
    teams,
    filters,
    error,
}: PlayerHistoryTableProps) {
    const [deleting, setDeleting] = useState<number | null>(null);
    const [sheetOpen, setSheetOpen] = useState(false);
    const [editingHistory, setEditingHistory] = useState<PlayerHistory | undefined>(undefined);

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

    return (
        <div className="space-y-4">
            {/* ── Toolbar: filters + Add Game ─────────────────────────── */}
            <div className="flex flex-wrap items-center gap-3">
                {/* Date range */}
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

                {/* Opponent filter */}
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

                {/* Spacer */}
                <div className="flex-1" />

                {/* Add Game CTA */}
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

            {/* ── Table ───────────────────────────────────────────────── */}
            {histories.length > 0 && (
                <div className="overflow-x-auto rounded-xl border border-border bg-card">
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-muted/60 hover:bg-muted/60">
                                <TableHead className="sticky left-0 z-10 bg-muted/60 w-20 font-ui font-semibold text-xs uppercase tracking-wide">
                                    Actions
                                </TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">Date</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">Playing For</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">Opponent</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">Pos</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">MIN</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">PTS</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">REB</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">AST</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">STL</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">BLK</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">TO</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">FG</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">3PT</TableHead>
                                <TableHead className="whitespace-nowrap font-ui font-semibold text-xs uppercase tracking-wide">FT</TableHead>
                            </TableRow>
                        </TableHeader>

                        <TableBody>
                            {histories.map((h, i) => {
                                const rowBg = i % 2 === 0 ? '' : 'bg-muted/20';
                                const fgStr = h.field_goals_made !== null && h.field_goals_attempted !== null
                                    ? `${h.field_goals_made}-${h.field_goals_attempted}`
                                    : '—';
                                const tptStr = h.three_pointers_made !== null && h.three_pointers_attempted !== null
                                    ? `${h.three_pointers_made}-${h.three_pointers_attempted}`
                                    : '—';
                                const ftStr = h.free_throws_made !== null && h.free_throws_attempted !== null
                                    ? `${h.free_throws_made}-${h.free_throws_attempted}`
                                    : '—';

                                return (
                                    <TableRow
                                        key={h.id}
                                        className={`${rowBg} hover:bg-primary/5`}
                                    >
                                        {/* Actions */}
                                        <TableCell className="sticky left-0 z-10 bg-card">
                                            <div className="flex items-center gap-1">
                                                <button
                                                    type="button"
                                                    title="Edit game"
                                                    onClick={() => openEdit(h)}
                                                    className="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground transition-colors"
                                                >
                                                    <Pencil size={12} />
                                                </button>
                                                <button
                                                    title="Remove game"
                                                    disabled={deleting === h.id}
                                                    onClick={() => handleDelete(h)}
                                                    className="flex h-7 w-7 items-center justify-center rounded-md text-destructive hover:bg-muted transition-colors disabled:opacity-40"
                                                >
                                                    <Trash2 size={12} />
                                                </button>
                                            </div>
                                        </TableCell>

                                        <TableCell className="whitespace-nowrap text-sm">{fmtDate(h.game_date)}</TableCell>
                                        <TableCell className="whitespace-nowrap text-sm font-ui">
                                            {h.playing_team ? `${h.playing_team.code}` : '—'}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap text-sm font-ui text-muted-foreground">
                                            {h.opponent_team
                                                ? `${h.opponent_team.code} — ${h.opponent_team.name}`
                                                : '—'}
                                        </TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{h.position_played ?? '—'}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{fmt(h.minutes_played)}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm font-semibold">{fmt(h.points)}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{fmt(h.rebounds)}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{fmt(h.assists)}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{fmt(h.steals)}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{fmt(h.blocks)}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{fmt(h.turnovers)}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{fgStr}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{tptStr}</TableCell>
                                        <TableCell className="whitespace-nowrap font-mono text-sm">{ftStr}</TableCell>
                                    </TableRow>
                                );
                            })}
                        </TableBody>
                    </Table>
                </div>
            )}

            <PlayerHistorySheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                playerId={playerId}
                playerName={playerName}
                teams={teams}
                history={editingHistory}
            />
        </div>
    );
}
