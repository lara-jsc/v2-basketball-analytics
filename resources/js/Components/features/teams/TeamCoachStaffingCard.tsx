import { TeamCoachStaffingSheet } from '@/Components/features/teams/TeamCoachStaffingSheet';
import { type CoachOption, type PageProps, type Team, type TeamCoachSummary } from '@/types';
import { usePage } from '@inertiajs/react';
import { ClipboardList, Pencil, Shield, UserRound } from 'lucide-react';
import { useState } from 'react';

interface TeamCoachStaffingCardProps {
    team: Team;
    coachOptions: CoachOption[];
    mainCoach: TeamCoachSummary | null;
    assistantCoaches: TeamCoachSummary[];
}

export function TeamCoachStaffingCard({
    team,
    coachOptions,
    mainCoach,
    assistantCoaches,
}: TeamCoachStaffingCardProps) {
    const canManage = usePage<PageProps>().props.auth.user?.is_admin ?? false;
    const [open, setOpen] = useState(false);
    const hasEligibleCoaches = coachOptions.length > 0;

    return (
        <>
            <div className="rounded-xl border border-border bg-card">
                <div className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                    <div>
                        <p className="text-[10px] font-semibold uppercase tracking-[0.22em] text-muted-foreground">
                            Team Management
                        </p>
                        <h2 className="mt-1 font-display text-base font-bold tracking-wide text-foreground">
                            Coach Staffing
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Assign one main coach and set assistant defaults for live-game setup.
                        </p>
                    </div>

                    {canManage && (
                        <button
                            onClick={() => setOpen(true)}
                            className="flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-ui font-semibold tracking-wide text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        >
                            <Pencil size={12} />
                            Manage staffing
                        </button>
                    )}
                </div>

                <div className="grid gap-4 px-5 py-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                    <StaffingBlock
                        icon={<Shield size={14} className="text-accent" />}
                        label="Main Coach"
                        emptyLabel="No main coach assigned"
                        summary={mainCoach ? `${mainCoach.name} · ${mainCoach.email}` : null}
                    />

                    <StaffingBlock
                        icon={<UserRound size={14} className="text-accent" />}
                        label="Assistant Coaches"
                        emptyLabel="No assistant coaches assigned"
                        summary={formatAssistantCoachSummary(assistantCoaches)}
                        summaryTestId="assistant-coaches-summary"
                    />

                    <div className="rounded-lg border border-border bg-muted/20 px-4 py-3">
                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                            <ClipboardList size={13} />
                            Eligible Coaches
                        </div>
                        <div className="mt-2 text-2xl font-display font-bold text-foreground">
                            {coachOptions.length}
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {hasEligibleCoaches
                                ? 'Verified users on this team can be assigned here.'
                                : 'Create a coach account for this team under Accounts to enable staffing.'}
                        </p>
                    </div>
                </div>
            </div>

            <TeamCoachStaffingSheet
                open={open}
                onOpenChange={setOpen}
                team={team}
                coachOptions={coachOptions}
                mainCoach={mainCoach}
                assistantCoaches={assistantCoaches}
            />
        </>
    );
}

function StaffingBlock({
    icon,
    label,
    summary,
    emptyLabel,
    summaryTestId,
}: {
    icon: React.ReactNode;
    label: string;
    summary: string | null;
    emptyLabel: string;
    summaryTestId?: string;
}) {
    return (
        <div className="rounded-lg border border-border bg-muted/20 px-4 py-3">
            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-widest text-muted-foreground">
                {icon}
                {label}
            </div>
            <p data-testid={summaryTestId} className="mt-2 text-sm font-semibold text-foreground">
                {summary ?? emptyLabel}
            </p>
        </div>
    );
}

function formatAssistantCoachSummary(assistantCoaches: TeamCoachSummary[]): string | null {
    if (assistantCoaches.length === 0) {
        return null;
    }

    const names = assistantCoaches.map((coach) => coach.name);

    if (names.length <= 2) {
        return names.join(', ');
    }

    return `${names.length} assigned · ${names.join(', ')}`;
}
