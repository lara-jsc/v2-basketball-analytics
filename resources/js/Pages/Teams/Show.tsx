import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CsvUploadForm } from '@/Components/features/csv/CsvUploadForm';
import { ImportStatus } from '@/Components/features/csv/ImportStatus';
import { PlayerFormSheet } from '@/Components/features/players/PlayerFormSheet';
import { PlayersTable } from '@/Components/features/players/PlayersTable';
import { TeamFormSheet } from '@/Components/features/teams/TeamFormSheet';
import { type CsvImport, type PageProps, type PlayerWithStats, type Team } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronRight,
    HelpCircle,
    Pencil,
    Plus,
    Swords,
    X,
} from 'lucide-react';
import { useState } from 'react';

interface TeamsShowProps extends PageProps {
    team: Team;
    players: PlayerWithStats[];
    latestImport: CsvImport | null;
}

/**
 * Team detail page.
 *
 * Layout:
 *   1. Arena gradient header (team identity + actions)
 *   2. CSV upload — always visible at top, not hidden behind a toggle
 *   3. Import status
 *   4. Players table with column toggle
 *   5. Footer bar: computed team plus-minus + active player count
 *   6. Field legend: ? button → inline popover (no fixed sidebar)
 */
export default function TeamsShow({ team, players, latestImport }: TeamsShowProps) {
    const { flash } = usePage<TeamsShowProps>().props;
    const [addPlayerOpen, setAddPlayerOpen] = useState(false);
    const [editTeamOpen, setEditTeamOpen] = useState(false);
    const [legendOpen, setLegendOpen] = useState(false);

    // ── Team plus-minus: minutes-weighted avg of active players with a value
    const teamPlusMinus = computeTeamPlusMinus(players);
    const activePlayers = players.filter((p) => p.is_active);

    const logoUrl = team.logo_path ? `/storage/${team.logo_path}` : null;

    return (
        <AuthenticatedLayout>
            <Head title={team.name} />

            <div className="flex flex-col gap-5">
                {/* ── Arena header ──────────────────────────────────────── */}
                <div className="rounded-xl border border-border bg-card overflow-hidden">
                    <div className="relative flex items-center justify-between gap-4 px-5 py-4">
                        {/* Court texture overlay */}
                        <div className="pointer-events-none absolute inset-0 bg-[repeating-linear-gradient(90deg,transparent,transparent_48px,rgba(249,160,27,0.03)_48px,rgba(249,160,27,0.03)_49px)]" />
                        {/* Left: breadcrumb + team identity */}
                        <div className="flex items-center gap-4">
                            <Link
                                href={route('teams.index')}
                                className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors shrink-0"
                            >
                                <ArrowLeft size={14} />
                                Teams
                            </Link>
                            <span className="text-border">/</span>

                            {/* Logo */}
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-primary/10 transition-all hover:ring-2 hover:ring-accent/30">
                                {logoUrl ? (
                                    <img src={logoUrl} alt="" className="h-full w-full object-cover" />
                                ) : (
                                    <span className="font-display text-sm font-bold text-primary">
                                        {team.code.slice(0, 3)}
                                    </span>
                                )}
                            </div>

                            <div>
                                <h1 className="font-display text-xl font-bold tracking-wide text-foreground leading-none">
                                    {team.name}
                                </h1>
                                <div className="mt-0.5 flex items-center gap-2">
                                    <span className="font-mono text-xs text-muted-foreground">{team.code}</span>
                                    {!team.is_active && (
                                        <span className="rounded bg-muted px-1.5 py-0.5 text-[10px] font-ui font-semibold text-muted-foreground uppercase tracking-wide">
                                            Inactive
                                        </span>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Right: actions */}
                        <div className="flex items-center gap-2 shrink-0">
                            <button
                                onClick={() => setEditTeamOpen(true)}
                                className="flex items-center gap-1.5 rounded-lg border border-border bg-card px-3 py-1.5 text-xs font-ui font-semibold tracking-wide text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            >
                                <Pencil size={12} />
                                Edit Team
                            </button>
                            <button
                                onClick={() => setAddPlayerOpen(true)}
                                className="flex items-center gap-1.5 rounded-lg bg-primary px-3 py-1.5 text-xs font-ui font-semibold tracking-wide text-primary-foreground transition-opacity hover:opacity-90"
                            >
                                <Plus size={12} />
                                Add Player
                            </button>
                        </div>
                    </div>
                </div>

                {/* ── Flash ─────────────────────────────────────────────── */}
                {flash?.success && (
                    <div className="rounded-lg border border-green-700/40 bg-green-950/40 px-4 py-3 text-sm text-green-400">
                        {flash.success}
                    </div>
                )}

                {/* ── CSV Upload — always visible ────────────────────────── */}
                <CsvUploadForm teamId={team.id} />

                {/* ── Import status ──────────────────────────────────────── */}
                <ImportStatus latestImport={latestImport} />

                {/* ── Players section ────────────────────────────────────── */}
                <div className="flex flex-col gap-2">
                    {/* Section heading + legend toggle */}
                    <div className="flex items-center justify-between">
                        <h2 className="font-display text-base font-bold tracking-wide text-foreground">
                            Players
                        </h2>
                        <button
                            onClick={() => setLegendOpen((v) => !v)}
                            className="flex items-center gap-1.5 text-xs font-ui font-semibold tracking-wide text-muted-foreground hover:text-foreground transition-colors"
                        >
                            {legendOpen ? <X size={13} /> : <HelpCircle size={13} />}
                            {legendOpen ? 'Close legend' : 'Stat abbreviations'}
                        </button>
                    </div>

                    {/* Field legend — inline, toggled */}
                    {legendOpen && (
                        <div className="rounded-xl border border-border bg-card p-4">
                            <p className="font-ui text-[10px] font-semibold uppercase tracking-widest text-muted-foreground mb-3">
                                Stat Abbreviations
                            </p>
                            <div className="grid grid-cols-3 gap-x-6 gap-y-1.5 md:grid-cols-4">
                                {FIELD_LEGEND.map(({ abbr, desc }) => (
                                    <div key={abbr} className="flex items-baseline gap-1.5">
                                        <span className="font-mono text-[11px] font-bold text-accent shrink-0 w-14">
                                            {abbr}
                                        </span>
                                        <span className="text-[11px] text-muted-foreground">{desc}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Players table */}
                    <PlayersTable players={players} team={team} />
                </div>

                {/* ── Footer bar ─────────────────────────────────────────── */}
                <div className="flex items-center justify-between rounded-xl border border-border bg-card px-5 py-3 relative overflow-hidden">
                    <div className="pointer-events-none absolute inset-0 bg-gradient-to-r from-transparent via-transparent to-accent/5" />
                    <div className="flex items-center gap-6">
                        <FooterStat
                            label="Active Players"
                            value={String(activePlayers.length)}
                        />
                        <FooterStat
                            label="Total Roster"
                            value={String(players.length)}
                        />
                        <FooterStat
                            label="Team Plus-Minus"
                            value={teamPlusMinus !== null
                                ? (teamPlusMinus >= 0 ? `+${teamPlusMinus.toFixed(1)}` : teamPlusMinus.toFixed(1))
                                : '—'
                            }
                            accent
                        />
                    </div>

                    {/* CTA: go to comparison */}
                    {players.length > 0 && (
                        <Link
                            href={route('comparison.index')}
                            className="flex items-center gap-1.5 rounded-lg border border-accent/30 bg-accent/10 px-3 py-1.5 text-xs font-ui font-semibold tracking-wide text-accent transition-colors hover:bg-accent/20"
                        >
                            <Swords size={12} />
                            Compare Teams
                            <ChevronRight size={11} />
                        </Link>
                    )}
                </div>
            </div>

            {/* Add player drawer */}
            <PlayerFormSheet
                open={addPlayerOpen}
                onOpenChange={setAddPlayerOpen}
                team={team}
            />

            {/* Edit team drawer */}
            <TeamFormSheet
                open={editTeamOpen}
                onOpenChange={setEditTeamOpen}
                team={team}
            />
        </AuthenticatedLayout>
    );
}

// ── Internal components ───────────────────────────────────────────────────────

function FooterStat({
    label,
    value,
    accent = false,
}: {
    label: string;
    value: string;
    accent?: boolean;
}) {
    return (
        <div className="flex flex-col gap-0.5">
            <span className="font-ui text-[10px] font-semibold uppercase tracking-widest text-muted-foreground">
                {label}
            </span>
            <span className={`font-display text-lg font-bold leading-none ${accent ? 'text-accent drop-shadow-[0_0_6px_rgba(249,160,27,0.5)]' : 'text-foreground'}`}>
                {value}
            </span>
        </div>
    );
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Minutes-weighted average plus-minus across all active players.
 * Returns null if no active player has a computed plus_minus yet.
 */
function computeTeamPlusMinus(players: PlayerWithStats[]): number | null {
    const eligible = players.filter(
        (p) => p.is_active && p.stats[0]?.plus_minus != null && p.stats[0]?.min != null,
    );
    if (eligible.length === 0) return null;

    const totalMin = eligible.reduce((sum, p) => sum + (p.stats[0]?.min ?? 0), 0);
    if (totalMin === 0) return null;

    const weighted = eligible.reduce(
        (sum, p) => sum + (p.stats[0]?.plus_minus ?? 0) * (p.stats[0]?.min ?? 0),
        0,
    );

    return weighted / totalMin;
}

// ── Field legend data ─────────────────────────────────────────────────────────

const FIELD_LEGEND = [
    { abbr: '+/-',    desc: 'Plus-Minus (BPM)' },
    { abbr: 'PTS',    desc: 'Points per game' },
    { abbr: 'REB',    desc: 'Rebounds per game' },
    { abbr: 'AST',    desc: 'Assists per game' },
    { abbr: 'BLK',    desc: 'Blocks per game' },
    { abbr: 'STL',    desc: 'Steals per game' },
    { abbr: 'MIN',    desc: 'Minutes per game' },
    { abbr: 'FG%',    desc: 'Field goal %' },
    { abbr: 'FG',     desc: 'FG made-attempted' },
    { abbr: '3P%',    desc: '3-point FG %' },
    { abbr: '3PT',    desc: '3PT made-attempted' },
    { abbr: 'FT%',    desc: 'Free throw %' },
    { abbr: 'FT',     desc: 'FT made-attempted' },
    { abbr: 'SC-EFF', desc: 'Scoring efficiency' },
    { abbr: 'SH-EFF', desc: 'Shooting efficiency' },
    { abbr: 'DR',     desc: 'Defensive rebounds' },
    { abbr: 'OR',     desc: 'Offensive rebounds' },
    { abbr: 'TO',     desc: 'Turnovers per game' },
    { abbr: 'AST/TO', desc: 'Assist-to-turnover' },
    { abbr: 'STL/TO', desc: 'Steal-to-turnover' },
    { abbr: 'PF',     desc: 'Fouls per game' },
    { abbr: 'FLAG',   desc: 'Flagrant fouls' },
    { abbr: 'TECH',   desc: 'Technical fouls' },
    { abbr: 'EJECT',  desc: 'Ejections' },
    { abbr: 'DQ',     desc: 'Disqualifications' },
    { abbr: 'GP',     desc: 'Games played' },
    { abbr: 'GS',     desc: 'Games started' },
    { abbr: 'DD2',    desc: 'Double-doubles' },
    { abbr: 'TD3',    desc: 'Triple-doubles' },
    { abbr: 'PC',     desc: 'Position on court' },
    { abbr: 'SD',     desc: 'Spatial data' },
];
