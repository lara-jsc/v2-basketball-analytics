import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PlayerHistoryForm } from '@/Components/features/players/PlayerHistoryForm';
import type { PlayerHistoryCreateProps } from '@/types/PlayerHistory.types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';

export default function PlayerHistoriesCreate({ player, teams }: PlayerHistoryCreateProps) {
    return (
        <AuthenticatedLayout>
            <Head title={`Add Game — ${player.first_name} ${player.last_name}`} />

            <div className="flex flex-col gap-5">
                {/* ── Arena header ──────────────────────────────────────── */}
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <div className="flex items-center justify-between gap-4 px-5 py-4">
                        <div className="flex items-center gap-4">
                            <Link
                                href={route('player-histories.index', player.id)}
                                className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors shrink-0"
                            >
                                <ArrowLeft size={14} />
                                Back
                            </Link>

                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Link href={route('teams.show', player.team_id)} className="hover:text-foreground transition-colors">
                                    {player.team?.name ?? '—'}
                                </Link>
                                <ChevronRight size={12} />
                                <Link href={route('player-histories.index', player.id)} className="hover:text-foreground transition-colors">
                                    {player.first_name} {player.last_name}
                                </Link>
                                <ChevronRight size={12} />
                                <span className="text-foreground font-medium">Add Game</span>
                            </div>
                        </div>

                        <p className="font-display text-sm font-bold tracking-wide text-foreground uppercase">
                            Add Game Entry
                        </p>
                    </div>
                </div>

                {/* ── Form card ─────────────────────────────────────────── */}
                <div className="rounded-xl border border-border bg-card overflow-hidden max-w-2xl">
                    <div className="border-b border-border px-5 py-3">
                        <h2 className="font-display text-sm font-bold tracking-widest uppercase text-foreground">Game Entry Form</h2>
                    </div>
                    <div className="px-5 py-5">
                    <PlayerHistoryForm
                        action={route('player-histories.store', player.id)}
                        method="post"
                        teams={teams}
                        onSuccess={() => router.visit(route('player-histories.index', player.id))}
                    />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
