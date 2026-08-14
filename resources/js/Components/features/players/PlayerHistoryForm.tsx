import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Separator } from '@/Components/ui/separator';
import type { PlayerHistory, PlayerHistoryFormData } from '@/types/PlayerHistory.types';
import type { Team } from '@/types';
import { useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';

interface PlayerHistoryFormProps {
    /** Route to POST (create) or PUT (edit) */
    action: string;
    method: 'post' | 'put';
    playingTeam: Pick<Team, 'id' | 'code' | 'name'>;
    opponentTeams: Pick<Team, 'id' | 'code' | 'name'>[];
    /** Pre-populated when editing */
    history?: PlayerHistory;
    onSuccess?: () => void;
}

function toStr(value: number | null | undefined): string {
    return value !== null && value !== undefined ? String(value) : '';
}

/**
 * Shared form for creating and editing a single game history entry.
 * Used by both Players/Histories/Create and Players/Histories/Edit pages.
 */
export function PlayerHistoryForm({ action, method, playingTeam, opponentTeams, history, onSuccess }: PlayerHistoryFormProps) {
    const { data, setData, post, put, processing, errors } = useForm<PlayerHistoryFormData>({
        opponent_team_id:         history?.opponent_team_id ?? '',
        game_date:                history?.game_date        ?? '',
        position_played:          history?.position_played  ?? '',
        minutes_played:           toStr(history?.minutes_played),
        points:                   toStr(history?.points),
        field_goals_made:         toStr(history?.field_goals_made),
        field_goals_attempted:    toStr(history?.field_goals_attempted),
        three_pointers_made:      toStr(history?.three_pointers_made),
        three_pointers_attempted: toStr(history?.three_pointers_attempted),
        free_throws_made:         toStr(history?.free_throws_made),
        free_throws_attempted:    toStr(history?.free_throws_attempted),
        offensive_rebounds:       toStr(history?.offensive_rebounds),
        defensive_rebounds:       toStr(history?.defensive_rebounds),
        rebounds:                 toStr(history?.rebounds),
        assists:                  toStr(history?.assists),
        steals:                   toStr(history?.steals),
        blocks:                   toStr(history?.blocks),
        turnovers:                toStr(history?.turnovers),
        personal_fouls:           toStr(history?.personal_fouls),
        flagrant_fouls:           toStr(history?.flagrant_fouls),
        technical_fouls:          toStr(history?.technical_fouls),
        ejections:                toStr(history?.ejections),
        disqualifications:        toStr(history?.disqualifications),
        is_started:               history?.is_started ?? false,
        notes:                    history?.notes ?? '',
    });

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        const submit = method === 'put' ? put : post;
        submit(action, { onSuccess });
    };

    const numField = (key: keyof PlayerHistoryFormData, label: string) => (
        <div className="space-y-1.5">
            <Label htmlFor={key} className="text-xs">{label}</Label>
            <Input
                id={key}
                type="number"
                min={0}
                value={data[key] as string}
                onChange={(e) => setData(key, e.target.value)}
                className="h-8 text-sm font-mono"
                placeholder="—"
            />
            {errors[key] && <p className="text-xs text-destructive">{errors[key]}</p>}
        </div>
    );

    return (
        <form id="history-form" onSubmit={handleSubmit}>
            <div className="space-y-6">

                    {/* ── Game Context ────────────────────────────────────── */}
                    <section className="space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Game Info
                        </h3>
                        <div className="grid grid-cols-2 gap-3">

                            {/* Game date */}
                            <div className="space-y-1.5 col-span-2 sm:col-span-1">
                                <Label htmlFor="game_date" className="text-xs">Game Date *</Label>
                                <Input
                                    id="game_date"
                                    type="date"
                                    value={data.game_date}
                                    onChange={(e) => setData('game_date', e.target.value)}
                                    className="h-8 text-sm"
                                    required
                                />
                                {errors.game_date && <p className="text-xs text-destructive">{errors.game_date}</p>}
                            </div>

                            {/* Position */}
                            <div className="space-y-1.5 col-span-2 sm:col-span-1">
                                <Label htmlFor="position_played" className="text-xs">Position Played</Label>
                                <Input
                                    id="position_played"
                                    type="text"
                                    value={data.position_played}
                                    onChange={(e) => setData('position_played', e.target.value)}
                                    className="h-8 text-sm"
                                    placeholder="e.g. PG"
                                />
                                {errors.position_played && <p className="text-xs text-destructive">{errors.position_played}</p>}
                            </div>

                            {/* Playing team */}
                            <div className="space-y-1.5">
                                <Label className="text-xs">Playing For</Label>
                                <div className="flex h-8 items-center rounded-md border border-border bg-muted/30 px-3 text-sm text-foreground">
                                    {playingTeam.code} — {playingTeam.name}
                                </div>
                                <p className="text-[11px] text-muted-foreground">
                                    Manual entries are recorded for the player current team.
                                </p>
                            </div>

                            {/* Opponent team */}
                            <div className="space-y-1.5">
                                <Label htmlFor="opponent_team_id" className="text-xs">Against *</Label>
                                <Select
                                    value={data.opponent_team_id !== '' ? String(data.opponent_team_id) : ''}
                                    onValueChange={(v) => setData('opponent_team_id', Number(v))}
                                >
                                    <SelectTrigger id="opponent_team_id" className="h-8 text-sm">
                                        <SelectValue placeholder="Select opponent…" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {opponentTeams.map((t) => (
                                            <SelectItem key={t.id} value={String(t.id)}>
                                                {t.code} — {t.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.opponent_team_id && <p className="text-xs text-destructive">{errors.opponent_team_id}</p>}
                            </div>

                            {/* Minutes played */}
                            <div className="space-y-1.5">
                                <Label htmlFor="minutes_played" className="text-xs">Minutes Played</Label>
                                <Input
                                    id="minutes_played"
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    value={data.minutes_played}
                                    onChange={(e) => setData('minutes_played', e.target.value)}
                                    className="h-8 text-sm font-mono"
                                    placeholder="—"
                                />
                                {errors.minutes_played && <p className="text-xs text-destructive">{errors.minutes_played}</p>}
                            </div>

                            {/* Started */}
                            <div className="space-y-1.5">
                                <Label htmlFor="is_started" className="text-xs">Started?</Label>
                                <Select
                                    value={data.is_started ? 'true' : 'false'}
                                    onValueChange={(v) => setData('is_started', v === 'true')}
                                >
                                    <SelectTrigger id="is_started" className="h-8 text-sm">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="true">Yes</SelectItem>
                                        <SelectItem value="false">No</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </section>

                    <Separator />

                    {/* ── Scoring ─────────────────────────────────────────── */}
                    <section className="space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Scoring
                        </h3>
                        <div className="grid grid-cols-3 gap-3">
                            {numField('points', 'PTS')}
                            {numField('field_goals_made', 'FGM')}
                            {numField('field_goals_attempted', 'FGA')}
                            {numField('three_pointers_made', '3PM')}
                            {numField('three_pointers_attempted', '3PA')}
                            {numField('free_throws_made', 'FTM')}
                            {numField('free_throws_attempted', 'FTA')}
                        </div>
                    </section>

                    <Separator />

                    {/* ── Rebounds ────────────────────────────────────────── */}
                    <section className="space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Rebounds
                        </h3>
                        <div className="grid grid-cols-3 gap-3">
                            {numField('rebounds', 'REB')}
                            {numField('defensive_rebounds', 'DR')}
                            {numField('offensive_rebounds', 'OR')}
                        </div>
                    </section>

                    <Separator />

                    {/* ── Other Counting Stats ─────────────────────────────── */}
                    <section className="space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Other Stats
                        </h3>
                        <div className="grid grid-cols-3 gap-3">
                            {numField('assists', 'AST')}
                            {numField('steals', 'STL')}
                            {numField('blocks', 'BLK')}
                            {numField('turnovers', 'TO')}
                            {numField('personal_fouls', 'PF')}
                        </div>
                    </section>

                    <Separator />

                    {/* ── Discipline ───────────────────────────────────────── */}
                    <section className="space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Discipline
                        </h3>
                        <div className="grid grid-cols-3 gap-3">
                            {numField('flagrant_fouls', 'FLAG')}
                            {numField('technical_fouls', 'TECH')}
                            {numField('ejections', 'EJECT')}
                            {numField('disqualifications', 'DQ')}
                        </div>
                    </section>

                    <Separator />

                    {/* ── Notes ───────────────────────────────────────────── */}
                    <section className="space-y-4">
                        <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Notes
                        </h3>
                        <div className="space-y-1.5">
                            <Label htmlFor="notes" className="text-xs">Coaching Notes</Label>
                            <textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e) => setData('notes', e.target.value)}
                                rows={3}
                                placeholder="Optional notes for this game entry…"
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-none"
                            />
                            {errors.notes && <p className="text-xs text-destructive">{errors.notes}</p>}
                        </div>
                    </section>

            </div>

            {/* ── Footer actions ──────────────────────────────────────────── */}
            <div className="flex items-center justify-end gap-3 border-t border-border pt-4 mt-6">
                <Button
                    type="submit"
                    form="history-form"
                    disabled={processing}
                    className="bg-primary hover:bg-primary/90 text-primary-foreground"
                >
                    {processing ? 'Saving…' : history ? 'Save Changes' : 'Add Game'}
                </Button>
            </div>
        </form>
    );
}
