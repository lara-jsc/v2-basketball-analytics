import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { ScrollArea } from '@/Components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/Components/ui/sheet';
import { type CoachOption, type Team, type TeamCoachSummary } from '@/types';
import { useForm } from '@inertiajs/react';
import { useEffect, useRef, type FormEventHandler } from 'react';

interface TeamCoachStaffingSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    team: Team;
    coachOptions: CoachOption[];
    mainCoach: TeamCoachSummary | null;
    assistantCoaches: TeamCoachSummary[];
}

interface TeamCoachStaffingFormData {
    main_coach_user_id: string;
    assistant_coach_user_ids: number[];
}

export function TeamCoachStaffingSheet({
    open,
    onOpenChange,
    team,
    coachOptions,
    mainCoach,
    assistantCoaches,
}: TeamCoachStaffingSheetProps) {
    const { data, setData, put, processing, errors, clearErrors, transform } = useForm<TeamCoachStaffingFormData>(
        buildStaffingFormData(mainCoach, assistantCoaches),
    );
    const wasOpenRef = useRef(open);

    useEffect(() => {
        if (open && !wasOpenRef.current) {
            setData(buildStaffingFormData(mainCoach, assistantCoaches));
            clearErrors();
        }

        wasOpenRef.current = open;
    }, [open, mainCoach, assistantCoaches, setData, clearErrors]);

    const handleSubmit: FormEventHandler = (event) => {
        event.preventDefault();

        transform((currentData) => ({
            main_coach_user_id:
                currentData.main_coach_user_id === '' ? null : Number(currentData.main_coach_user_id),
            assistant_coach_user_ids: currentData.assistant_coach_user_ids.map((id) => Number(id)),
        }));

        put(route('teams.staffing.update', { team: team.id }), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onFinish: () => transform((currentData) => currentData),
        });
    };

    function handleMainCoachChange(value: string): void {
        setData((current) => ({
            ...current,
            main_coach_user_id: value,
            assistant_coach_user_ids: current.assistant_coach_user_ids.filter((id) => String(id) !== value),
        }));
    }

    function toggleAssistant(coachId: number): void {
        setData((current) => {
            const exists = current.assistant_coach_user_ids.includes(coachId);

            return {
                ...current,
                assistant_coach_user_ids: exists
                    ? current.assistant_coach_user_ids.filter((id) => id !== coachId)
                    : [...current.assistant_coach_user_ids, coachId],
            };
        });
    }

    const noEligibleCoaches = coachOptions.length === 0;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right" className="flex w-full flex-col p-0 sm:max-w-md">
                <SheetHeader className="shrink-0 border-b border-border px-6 pb-4 pt-6">
                    <SheetTitle>Manage Coach Staffing</SheetTitle>
                    <SheetDescription>
                        Choose one main coach and any assistant coaches from {team.name}&apos;s verified users.
                    </SheetDescription>
                </SheetHeader>

                <ScrollArea className="min-h-0 flex-1">
                    <form id="team-coach-staffing-form" onSubmit={handleSubmit} className="space-y-6 px-6 py-5">
                        {noEligibleCoaches ? (
                            <div className="rounded-lg border border-dashed border-border bg-muted/30 px-4 py-4 text-sm text-muted-foreground">
                                No verified team users are available yet. Verify a user on this team to assign staffing.
                            </div>
                        ) : (
                            <>
                                <section className="space-y-2">
                                    <Label htmlFor="main-coach" className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                        Main Coach
                                    </Label>
                                    <select
                                        id="main-coach"
                                        value={data.main_coach_user_id}
                                        onChange={(event) => handleMainCoachChange(event.target.value)}
                                        className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cyan-300"
                                    >
                                        <option value="">No main coach assigned</option>
                                        {coachOptions.map((coach) => (
                                            <option key={coach.id} value={coach.id}>
                                                {coach.name}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.main_coach_user_id && (
                                        <p className="text-xs text-destructive">{errors.main_coach_user_id}</p>
                                    )}
                                </section>

                                <section className="space-y-3">
                                    <div>
                                        <p className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                                            Assistant Coaches
                                        </p>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            Select any number of assistants. The main coach cannot also be selected here.
                                        </p>
                                    </div>

                                    <div className="space-y-2">
                                        {coachOptions.map((coach) => {
                                            const disabled = data.main_coach_user_id === String(coach.id);
                                            const checked = data.assistant_coach_user_ids.includes(coach.id);

                                            return (
                                                <label
                                                    key={coach.id}
                                                    className={`flex items-start gap-3 rounded-md border px-3 py-3 transition-colors ${
                                                        disabled ? 'border-border/60 bg-muted/30 text-muted-foreground' : 'border-border hover:bg-muted/30'
                                                    }`}
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={checked}
                                                        disabled={disabled}
                                                        onChange={() => toggleAssistant(coach.id)}
                                                        className="mt-0.5 h-4 w-4 accent-amber-400"
                                                    />
                                                    <span className="min-w-0">
                                                        <span className="block truncate text-sm font-semibold text-foreground">
                                                            {coach.name}
                                                        </span>
                                                        <span className="block truncate text-xs text-muted-foreground">
                                                            {coach.email}
                                                        </span>
                                                    </span>
                                                </label>
                                            );
                                        })}
                                    </div>

                                    {errors.assistant_coach_user_ids && (
                                        <p className="text-xs text-destructive">{errors.assistant_coach_user_ids}</p>
                                    )}
                                </section>
                            </>
                        )}
                    </form>
                </ScrollArea>

                <div className="flex shrink-0 items-center justify-end gap-3 border-t border-border px-6 py-4">
                    <Button type="button" variant="outline" size="sm" onClick={() => onOpenChange(false)}>
                        Cancel
                    </Button>
                    <Button type="submit" form="team-coach-staffing-form" size="sm" disabled={processing || noEligibleCoaches}>
                        {processing ? 'Saving…' : 'Save Staffing'}
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}

function buildStaffingFormData(
    mainCoach: TeamCoachSummary | null,
    assistantCoaches: TeamCoachSummary[],
): TeamCoachStaffingFormData {
    return {
        main_coach_user_id: mainCoach?.id ? String(mainCoach.id) : '',
        assistant_coach_user_ids: assistantCoaches.map((coach) => coach.id),
    };
}
