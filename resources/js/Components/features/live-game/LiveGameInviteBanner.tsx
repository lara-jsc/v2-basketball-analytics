import type { LiveGameInviteBanner as Invite } from '@/types';
import { Link, router } from '@inertiajs/react';
import { Radio, X } from 'lucide-react';

type LiveGameInviteBannerProps = {
    invite: Invite;
};

export function LiveGameInviteBanner({ invite }: LiveGameInviteBannerProps) {
    function dismiss(): void {
        router.post(route('live-game-invites.dismiss', { notification: invite.id }), {}, {
            preserveScroll: true,
            only: ['liveGameInvite'],
        });
    }

    return (
        <div
            role="dialog"
            aria-label={invite.title}
            className="relative z-20 border-b border-cyan-300/40 bg-cyan-300/15 px-4 py-4 backdrop-blur-sm sm:px-6"
        >
            <div className="mx-auto flex max-w-5xl flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="flex min-w-0 items-start gap-3">
                    <span className="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-cyan-300/40 bg-cyan-300/10 text-cyan-100">
                        <Radio size={18} />
                    </span>
                    <div className="min-w-0">
                        <p className="text-sm font-bold uppercase tracking-[0.1em] text-cyan-100">{invite.title}</p>
                        <p className="mt-1 text-sm text-foreground">{invite.body}</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {invite.home_team_name} vs {invite.opponent_team_name}
                        </p>
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2 sm:shrink-0">
                    <Link
                        href={route('live-games.show', { liveGame: invite.live_game_id })}
                        className="flex min-h-11 items-center justify-center rounded-md bg-amber-400 px-4 text-xs font-bold uppercase tracking-wide text-black transition-colors hover:bg-amber-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        Open game
                    </Link>
                    <button
                        type="button"
                        onClick={dismiss}
                        className="flex min-h-11 items-center gap-2 rounded-md border border-border px-4 text-xs font-bold uppercase tracking-wide text-muted-foreground transition-colors hover:bg-muted/40 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                    >
                        <X size={14} /> Dismiss
                    </button>
                </div>
            </div>
        </div>
    );
}
