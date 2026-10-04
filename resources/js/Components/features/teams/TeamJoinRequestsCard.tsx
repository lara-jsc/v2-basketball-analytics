import { type PageProps, type TeamJoinRequestSummary } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, Loader2, X } from 'lucide-react';
import { type KeyboardEvent, useRef, useState } from 'react';

interface TeamJoinRequestsCardProps {
    teamId: number;
    requests: TeamJoinRequestSummary[];
}

type Action = 'approve' | 'reject';

const secondaryButton =
    'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border border-border px-3 text-sm font-ui font-semibold tracking-wide text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50';
const primaryButton =
    'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-primary px-3 text-sm font-ui font-semibold tracking-wide text-primary-foreground transition-opacity hover:opacity-90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50';
const dangerButton =
    'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg border border-destructive/60 px-3 text-sm font-ui font-semibold tracking-wide text-red-700 transition-colors hover:bg-destructive/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring dark:text-red-400 disabled:opacity-50';

/**
 * Pending assistant-coach join requests. Shown to the team's main coach and admins.
 */
export function TeamJoinRequestsCard({ teamId, requests }: TeamJoinRequestsCardProps) {
    const errors = usePage<PageProps & { errors: Record<string, string> }>().props.errors;
    const [busy, setBusy] = useState<{ id: number; action: Action } | null>(null);
    const [confirmingId, setConfirmingId] = useState<number | null>(null);
    const declineTriggers = useRef(new Map<number, HTMLButtonElement>());

    const closeConfirm = (id: number) => {
        setConfirmingId(null);
        // Return focus to the row's Decline trigger, never the neighbouring Approve.
        requestAnimationFrame(() => declineTriggers.current.get(id)?.focus());
    };

    const onConfirmKeyDown = (event: KeyboardEvent<HTMLDivElement>, id: number) => {
        if (event.key === 'Escape') closeConfirm(id);
    };

    const decide = (id: number, action: Action) => {
        setBusy({ id, action });
        router.post(
            route(`teams.join-requests.${action}`, { team: teamId, joinRequest: id }),
            {},
            {
                preserveScroll: true,
                onFinish: () => {
                    setBusy(null);
                    setConfirmingId(null);
                },
            },
        );
    };

    const spinnerFor = (id: number, action: Action) =>
        busy?.id === id && busy.action === action ? <Loader2 size={14} className="animate-spin" aria-hidden /> : null;

    return (
        <section id="join-requests" aria-labelledby="join-requests-title" className="scroll-mt-6 rounded-xl border border-border bg-card">
            <div className="border-b border-border px-5 py-4">
                <h2 id="join-requests-title" className="font-display text-base font-bold tracking-wide text-foreground">
                    Join Requests
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Approved coaches join as assistant coaches.
                </p>
            </div>

            {errors?.join_request && (
                <p role="alert" className="border-b border-border px-5 py-3 text-sm text-destructive">
                    {errors.join_request}
                </p>
            )}

            {requests.length === 0 ? (
                <p className="px-5 py-4 text-sm text-muted-foreground">
                    No one is waiting. Assistant coaches who sign up for this team appear here.
                </p>
            ) : (
                <ul className="divide-y divide-border">
                    {requests.map((request) => {
                        const confirming = confirmingId === request.id;

                        return (
                            <li key={request.id} className="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                                <div className="min-w-0">
                                    <p className="truncate text-sm font-semibold text-foreground">{request.user.name}</p>
                                    <p className="truncate text-xs text-muted-foreground">
                                        {request.user.email} · requested {formatDate(request.created_at)}
                                    </p>
                                </div>

                                {confirming ? (
                                    <div
                                        className="flex shrink-0 items-center gap-2"
                                        role="group"
                                        aria-label={`Confirm declining ${request.user.name}`}
                                        onKeyDown={(event) => onConfirmKeyDown(event, request.id)}
                                    >
                                        <span className="text-sm text-foreground">Decline {request.user.name}?</span>
                                        <button
                                            type="button"
                                            disabled={busy !== null}
                                            onClick={() => closeConfirm(request.id)}
                                            className={secondaryButton}
                                        >
                                            Keep request
                                        </button>
                                        <button
                                            type="button"
                                            disabled={busy !== null}
                                            onClick={() => decide(request.id, 'reject')}
                                            className={dangerButton}
                                            autoFocus
                                        >
                                            {spinnerFor(request.id, 'reject')}
                                            Decline
                                        </button>
                                    </div>
                                ) : (
                                    <div className="flex shrink-0 gap-2">
                                        <button
                                            type="button"
                                            disabled={busy !== null}
                                            ref={(element) => {
                                                if (element) declineTriggers.current.set(request.id, element);
                                                else declineTriggers.current.delete(request.id);
                                            }}
                                            onClick={() => setConfirmingId(request.id)}
                                            aria-label={`Decline ${request.user.name}`}
                                            className={secondaryButton}
                                        >
                                            <X size={14} aria-hidden />
                                            Decline
                                        </button>
                                        <button
                                            type="button"
                                            disabled={busy !== null}
                                            onClick={() => decide(request.id, 'approve')}
                                            aria-label={`Approve ${request.user.name}`}
                                            className={primaryButton}
                                        >
                                            {spinnerFor(request.id, 'approve') ?? <Check size={14} aria-hidden />}
                                            Approve
                                        </button>
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>
            )}
        </section>
    );
}

function formatDate(iso: string): string {
    return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
}
