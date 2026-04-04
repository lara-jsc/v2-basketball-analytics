import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PlayerHistoryForm } from '@/Components/features/players/PlayerHistoryForm';
import type { PlayerHistoryEditProps } from '@/types/PlayerHistory.types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ChevronRight } from 'lucide-react';

export default function PlayerHistoriesEdit({ history, teams }: PlayerHistoryEditProps) {
    const player = history.player;

    return (
        <AuthenticatedLayout>
            <Head title={`Edit Game — ${player.first_name} ${player.last_name}`} />

            <div className="flex flex-col gap-5">
                {/* ── Arena header ──────────────────────────────────────── */}
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <div className="flex items-center justify-between gap-4 bg-gradient-to-r from-primary/20 via-card to-card px-5 py-4">
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
                                <span className="text-foreground font-medium">Edit Game</span>
                            </div>
                        </div>

                        <div className="text-right">
                            <p className="font-display text-sm font-bold tracking-wide text-foreground uppercase">
                                Edit Game Entry
                            </p>
                            <p className="text-xs text-muted-foreground font-ui mt-0.5">
                                vs {history.opponent_team.code} — {history.opponent_team.name}
                                {' · '}
                                {new Date(history.game_date).toLocaleDateString('en-US', {
                                    year: 'numeric', month: 'short', day: 'numeric',
                                })}
                            </p>
                        </div>
                    </div>
                </div>

                {/* ── Delete warning ────────────────────────────────────── */}
                <div className="rounded-lg border border-border bg-muted/20 px-4 py-3 text-xs text-muted-foreground font-ui">
                    Saving changes will recalculate this player's aggregate stats. The +/- value will be recomputed automatically.
                </div>

                {/* ── Form card ─────────────────────────────────────────── */}
                <div className="rounded-xl border border-border bg-card px-5 py-5 max-w-2xl">
                    <PlayerHistoryForm
                        action={route('player-histories.update', history.id)}
                        method="put"
                        teams={teams}
                        history={history}
                        onSuccess={() => router.visit(route('player-histories.index', player.id))}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
