import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PlayerHistoryImport } from '@/Components/features/players/PlayerHistoryImport';
import { PlayerHistoryTable } from '@/Components/features/players/PlayerHistoryTable';
import type { PlayerHistoryIndexProps } from '@/types/PlayerHistory.types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';

export default function PlayerHistoriesIndex({
    player,
    histories,
    teams,
    filters,
}: PlayerHistoryIndexProps) {
    const { flash } = usePage<PlayerHistoryIndexProps>().props;

    return (
        <AuthenticatedLayout>
            <Head title={`${player.first_name} ${player.last_name} — Game History`} />

            <div className="flex flex-col gap-5">
                {/* ── Arena header ──────────────────────────────────────── */}
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <div className="relative flex items-center justify-between gap-4 bg-gradient-to-r from-primary/30 via-primary/10 to-transparent px-5 py-4">
                        <div className="pointer-events-none absolute inset-0 bg-[repeating-linear-gradient(90deg,transparent,transparent_48px,rgba(249,160,27,0.03)_48px,rgba(249,160,27,0.03)_49px)]" />

                        {/* Breadcrumb + player identity */}
                        <div className="flex items-center gap-4">
                            <Link
                                href={route('teams.show', player.team_id)}
                                className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors shrink-0"
                            >
                                <ArrowLeft size={14} />
                                Back
                            </Link>

                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Link href={route('teams.index')} className="hover:text-foreground transition-colors">
                                    Teams
                                </Link>
                                <ChevronRight size={12} />
                                <Link href={route('teams.show', player.team_id)} className="hover:text-foreground transition-colors">
                                    {player.team?.name ?? '—'}
                                </Link>
                                <ChevronRight size={12} />
                                <span className="text-foreground font-medium">
                                    {player.first_name} {player.last_name}
                                </span>
                                <ChevronRight size={12} />
                                <span className="text-foreground">Game History</span>
                            </div>
                        </div>

                        {/* Player badge */}
                        <div className="flex items-center gap-3 relative">
                            <div className="text-right">
                                <p className="font-display text-base font-bold tracking-wide text-foreground uppercase leading-none">
                                    {player.first_name} {player.last_name}
                                </p>
                                <div className="mt-1 flex items-center justify-end gap-2">
                                    <span className="font-ui text-[11px] text-muted-foreground">
                                        #{player.jersey_number}
                                    </span>
                                    {player.role && (
                                        <span className="rounded border border-accent/30 bg-accent/10 px-1.5 py-0.5 font-ui text-[10px] font-bold uppercase tracking-widest text-accent">
                                            {player.role}
                                        </span>
                                    )}
                                    {player.team?.code && (
                                        <span className="rounded border border-primary/30 bg-primary/10 px-1.5 py-0.5 font-ui text-[10px] font-bold uppercase tracking-widest text-primary">
                                            {player.team.code}
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {/* ── Flash message ─────────────────────────────────────── */}
                {flash?.success && (
                    <div className="rounded-lg border border-accent/30 bg-accent/10 px-4 py-3 text-sm text-accent font-ui font-medium">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive font-ui font-medium">
                        {flash.error}
                    </div>
                )}

                {/* ── CSV Import ────────────────────────────────────────── */}
                <PlayerHistoryImport playerId={player.id} />

                {/* ── History table ─────────────────────────────────────── */}
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <div className="flex items-center justify-between border-b border-border bg-gradient-to-r from-primary/15 to-transparent px-5 py-3">
                        <div>
                            <h2 className="font-display text-sm font-bold tracking-widest text-foreground uppercase">
                                Game Log
                            </h2>
                            <p className="text-xs text-muted-foreground mt-0.5 font-ui">
                                {histories.length} game{histories.length !== 1 ? 's' : ''} recorded
                            </p>
                        </div>
                    </div>
                    <div className="px-5 py-4">

                    <PlayerHistoryTable
                        playerId={player.id}
                        playerName={`${player.first_name} ${player.last_name}`}
                        histories={histories}
                        teams={teams}
                        filters={filters}
                    />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
