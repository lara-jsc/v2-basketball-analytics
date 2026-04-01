import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import {
    BarChart3,
    FileSpreadsheet,
    LayoutDashboard,
    NotebookText,
    ShieldCheck,
    Users,
} from 'lucide-react';
import { useMemo, useState } from 'react';

type NavKey =
    | 'dashboard'
    | 'teams'
    | 'players'
    | 'reports'
    | 'imports'
    | 'settings';

const navigation = [
    { key: 'dashboard', label: 'Dashboard', status: 'Live', icon: LayoutDashboard },
    { key: 'teams', label: 'Teams', status: 'Soon', icon: Users },
    { key: 'players', label: 'Players', status: 'Soon', icon: BarChart3 },
    { key: 'reports', label: 'Reports', status: 'Soon', icon: NotebookText },
    { key: 'imports', label: 'CSV Imports', status: 'Soon', icon: FileSpreadsheet },
    { key: 'settings', label: 'Settings', status: 'Soon', icon: ShieldCheck },
] satisfies {
    key: NavKey;
    label: string;
    status: string;
    icon: typeof LayoutDashboard;
}[];

const statsByTab: Record<
    NavKey,
    { label: string; value: string; note: string }[]
> = {
    dashboard: [
        { label: 'Games tracked', value: '128', note: '+12 this week' },
        { label: 'Win rate', value: '68.4%', note: '+4.1% last month' },
        { label: 'Offensive rating', value: '114.7', note: '+2.3 efficiency' },
        { label: 'Defensive rating', value: '102.9', note: '-1.6 allowed' },
    ],
    teams: [
        { label: 'Schools', value: '08', note: 'planned records' },
        { label: 'Teams', value: '16', note: 'varsity and reserve' },
        { label: 'Matchups', value: '12', note: 'stored opponent profiles' },
        { label: 'Status', value: 'Draft', note: 'schema comes next' },
    ],
    players: [
        { label: 'Roster size', value: '72', note: 'placeholder target' },
        { label: 'Tracked stats', value: '09', note: 'core metrics' },
        { label: 'Best five', value: '05', note: 'recommendation output' },
        { label: 'Data source', value: 'CSV', note: 'historical performance' },
    ],
    reports: [
        { label: 'PDF templates', value: '03', note: 'coach reports' },
        { label: 'XLSX exports', value: '02', note: 'analysis output' },
        { label: 'Snapshots', value: '14', note: 'saved recommendations' },
        { label: 'Mode', value: 'Ready', note: 'module placeholder' },
    ],
    imports: [
        { label: 'Accepted files', value: '.CSV', note: 'historical source' },
        { label: 'Validation', value: 'Queued', note: 'next backend step' },
        { label: 'Mappings', value: '05', note: 'fouls to defense' },
        { label: 'Process', value: 'Async', note: 'recommended approach' },
    ],
    settings: [
        { label: 'Theme', value: '2 modes', note: 'light and dark' },
        { label: 'Auth', value: 'Breeze', note: 'secured login flow' },
        { label: 'Server', value: 'Single', note: 'one VPS target' },
        { label: 'Analytics API', value: 'Local', note: 'python service' },
    ],
};

const centerContent: Record<
    NavKey,
    {
        title: string;
        description: string;
        bars: { label: string; sublabel: string; value: number }[];
        notes: string[];
        activityTitle: string;
        activityRows: { label: string; result: string; meta: string }[];
        leaderboardTitle: string;
        leaders: { name: string; team: string; value: string }[];
    }
> = {
    dashboard: {
        title: 'Welcome back, Test User',
        description:
            'Your login now opens a professional dashboard with team performance, player efficiency, and recent game tracking in a navy blue visual style.',
        bars: [
            { label: 'Blue Falcons', sublabel: 'Offensive Rating 118', value: 82 },
            { label: 'Harbor Knights', sublabel: 'Offensive Rating 111', value: 74 },
            { label: 'Capital Arrows', sublabel: 'Offensive Rating 108', value: 69 },
            { label: 'Metro Stallions', sublabel: 'Offensive Rating 104', value: 63 },
        ],
        notes: [
            'Half-court defense is holding opponents below 103 rating in the last 5 games.',
            'Transition offense improved after second-unit rotation changes.',
            'The next dashboard iteration can connect Python model output for shot quality.',
        ],
        activityTitle: 'Latest game results',
        activityRows: [
            { label: 'Blue Falcons vs Harbor Knights', result: 'W 94-81', meta: '98.7 pace' },
            { label: 'Capital Arrows vs Metro Stallions', result: 'W 88-79', meta: '95.1 pace' },
            { label: 'Blue Falcons vs Capital Arrows', result: 'L 86-89', meta: '96.4 pace' },
            { label: 'Harbor Knights vs Metro Stallions', result: 'W 102-90', meta: '100.2 pace' },
        ],
        leaderboardTitle: 'Top efficiency',
        leaders: [
            { name: 'Adrian Cruz', team: 'Blue Falcons', value: '29.8' },
            { name: 'Marco Santos', team: 'Harbor Knights', value: '26.4' },
            { name: 'Eli Navarro', team: 'Capital Arrows', value: '24.9' },
        ],
    },
    teams: {
        title: 'Team records will live here',
        description:
            'This panel can later manage schools, varsity rosters, opponent entries, and preparation details for each matchup.',
        bars: [
            { label: 'School registry', sublabel: 'master data preparation', value: 66 },
            { label: 'Team profiles', sublabel: 'coach and roster links', value: 54 },
            { label: 'Opponent scouting', sublabel: 'matchup notes', value: 48 },
            { label: 'Schedule structure', sublabel: 'future planning', value: 39 },
        ],
        notes: [
            'Use one school-to-many teams structure if junior and senior divisions are separate.',
            'Add opponent style tags so analytics can later account for matchup tendencies.',
            'Keep roster status simple first: active, injured, unavailable.',
        ],
        activityTitle: 'Planned team workspace',
        activityRows: [
            { label: 'School profile table', result: 'Draft', meta: 'schema first' },
            { label: 'Varsity team module', result: 'Pending', meta: 'CRUD ready next' },
            { label: 'Opponent registry', result: 'Pending', meta: 'for recommendations' },
            { label: 'Coach assignment', result: 'Optional', meta: 'if thesis scope allows' },
        ],
        leaderboardTitle: 'Priority modules',
        leaders: [
            { name: 'Schools', team: 'master data', value: '1' },
            { name: 'Teams', team: 'structure', value: '2' },
            { name: 'Opponents', team: 'context', value: '3' },
        ],
    },
    players: {
        title: 'Player tracking and efficiency view',
        description:
            'This section is a placeholder for individual player stats, role tagging, and best-five recommendation support.',
        bars: [
            { label: 'Shooting', sublabel: 'scoring impact', value: 88 },
            { label: 'Assists', sublabel: 'playmaking value', value: 67 },
            { label: 'Defense', sublabel: 'stops and coverage', value: 79 },
            { label: 'Penalty control', sublabel: 'fouls and dribbles', value: 58 },
        ],
        notes: [
            'Keep the first version interpretable so you can explain the recommendation model in your thesis.',
            'Store raw values and derived scores separately.',
            'You can later add position tags to improve lineup balance.',
        ],
        activityTitle: 'Planned player views',
        activityRows: [
            { label: 'Player profile cards', result: 'Pending', meta: 'basic info' },
            { label: 'Game stat history', result: 'Pending', meta: 'CSV-derived' },
            { label: 'Lineup score', result: 'Pending', meta: 'python output' },
            { label: 'Role labels', result: 'Pending', meta: 'guard wing center' },
        ],
        leaderboardTitle: 'Metric focus',
        leaders: [
            { name: 'Shooting', team: 'offense', value: '40%' },
            { name: 'Defense', team: 'stability', value: '30%' },
            { name: 'Assists', team: 'creation', value: '20%' },
        ],
    },
    reports: {
        title: 'Reports and printable summaries',
        description:
            'This area can hold the PDF and XLSX outputs you already know how to build well, making it a strong thesis feature.',
        bars: [
            { label: 'Game report', sublabel: 'coach-friendly export', value: 77 },
            { label: 'Lineup summary', sublabel: 'recommended starters', value: 69 },
            { label: 'Win-rate sheet', sublabel: 'prediction snapshot', value: 62 },
            { label: 'Player leaderboard', sublabel: 'top efficiency list', value: 58 },
        ],
        notes: [
            'Exports are a strong practical feature and easy to justify in the thesis.',
            'Keep filenames predictable so recommendation runs are reproducible.',
            'A summary cover page can help for panel presentation.',
        ],
        activityTitle: 'Suggested report types',
        activityRows: [
            { label: 'Team comparison', result: 'PDF', meta: 'presentation ready' },
            { label: 'Player efficiency sheet', result: 'XLSX', meta: 'sortable' },
            { label: 'Recommendation summary', result: 'PDF', meta: 'thesis appendix' },
            { label: 'Import audit log', result: 'CSV', meta: 'debug support' },
        ],
        leaderboardTitle: 'Export priority',
        leaders: [
            { name: 'Recommendation PDF', team: 'coach view', value: '1' },
            { name: 'Game summary', team: 'reporting', value: '2' },
            { name: 'Player efficiency', team: 'analysis', value: '3' },
        ],
    },
    imports: {
        title: 'CSV upload and data cleaning center',
        description:
            'This module is central to your April 16 milestone because all recommendation logic starts from imported historical records.',
        bars: [
            { label: 'Upload intake', sublabel: 'file acceptance', value: 85 },
            { label: 'Column validation', sublabel: 'required mappings', value: 72 },
            { label: 'Data cleaning', sublabel: 'normalization step', value: 61 },
            { label: 'Queue processing', sublabel: 'large-file safety', value: 52 },
        ],
        notes: [
            'Validate headers early and show a clear import error report.',
            'Save the raw CSV file and parsed summary separately.',
            'Asynchronous processing is safer if schools upload larger files.',
        ],
        activityTitle: 'Import pipeline',
        activityRows: [
            { label: 'Select CSV file', result: 'Ready', meta: 'UI next step' },
            { label: 'Map columns', result: 'Pending', meta: 'validation layer' },
            { label: 'Process records', result: 'Pending', meta: 'queue job' },
            { label: 'View import log', result: 'Pending', meta: 'history table' },
        ],
        leaderboardTitle: 'Required metrics',
        leaders: [
            { name: 'Shooting', team: 'positive', value: '+' },
            { name: 'Assists', team: 'positive', value: '+' },
            { name: 'Defensive play', team: 'positive', value: '+' },
        ],
    },
    settings: {
        title: 'System and appearance settings',
        description:
            'This placeholder confirms that both light mode and dark mode are already available while the rest of the settings module is still upcoming.',
        bars: [
            { label: 'Theme readiness', sublabel: 'light and dark mode', value: 100 },
            { label: 'Auth readiness', sublabel: 'login flow working', value: 90 },
            { label: 'Dashboard shell', sublabel: 'navigation and layout', value: 84 },
            { label: 'Model connection', sublabel: 'python endpoint next', value: 44 },
        ],
        notes: [
            'Theme preference is stored locally in the browser.',
            'Root route now sends guests straight to the login screen.',
            'Later you can add service health indicators here.',
        ],
        activityTitle: 'System checklist',
        activityRows: [
            { label: 'Theme toggle', result: 'Done', meta: 'local preference' },
            { label: 'Root redirect', result: 'Done', meta: 'login at /' },
            { label: 'Profile settings', result: 'Available', meta: 'Breeze module' },
            { label: 'Analytics connection', result: 'Next', meta: 'python service' },
        ],
        leaderboardTitle: 'Current status',
        leaders: [
            { name: 'Light Mode', team: 'enabled', value: 'ON' },
            { name: 'Dark Mode', team: 'enabled', value: 'ON' },
            { name: 'Dashboard shell', team: 'ready', value: 'OK' },
        ],
    },
};

export default function Dashboard() {
    const [activeTab, setActiveTab] = useState<NavKey>('dashboard');
    const content = useMemo(() => centerContent[activeTab], [activeTab]);
    const stats = useMemo(() => statsByTab[activeTab], [activeTab]);

    return (
        <AuthenticatedLayout>
            <Head title="Analytics Dashboard" />

            <div className="grid gap-4 xl:grid-cols-[180px_minmax(0,1fr)]">
                <aside className="rounded-[1.6rem] bg-[#1d336b] p-4 text-white shadow-sm dark:bg-slate-900">
                    <div className="space-y-5">
                        <div>
                            <p className="inline-flex rounded-full bg-white/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.28em] text-blue-100">
                                SPCF Style
                            </p>
                            <h2 className="mt-4 text-3xl font-semibold leading-tight">
                                Analytics Hub
                            </h2>
                            <p className="mt-3 text-sm leading-6 text-blue-50/80">
                                Clean, professional, and focused on game performance.
                            </p>
                        </div>

                        <nav className="space-y-2">
                            {navigation.map((item) => {
                                const Icon = item.icon;
                                const active = item.key === activeTab;

                                return (
                                    <button
                                        key={item.key}
                                        type="button"
                                        onClick={() => setActiveTab(item.key)}
                                        className={`flex w-full items-center justify-between rounded-2xl px-3 py-3 text-left transition ${
                                            active
                                                ? 'bg-white text-[#1d336b]'
                                                : 'bg-white/5 text-white hover:bg-white/10'
                                        }`}
                                    >
                                        <span className="flex items-center gap-3">
                                            <Icon className="h-4 w-4" />
                                            <span className="text-sm font-medium">{item.label}</span>
                                        </span>
                                        <span
                                            className={`text-[10px] uppercase tracking-[0.2em] ${
                                                active ? 'text-[#5472c8]' : 'text-blue-100/70'
                                            }`}
                                        >
                                            {item.status}
                                        </span>
                                    </button>
                                );
                            })}
                        </nav>

                        <div className="rounded-2xl bg-white/10 p-4">
                            <p className="text-xs text-blue-100/75">Signed in as</p>
                            <p className="mt-1 text-xl font-semibold">Test User</p>
                            <p className="mt-4 text-xs text-blue-100/70">test@example.com</p>
                            <p className="mt-4 rounded-xl bg-white px-3 py-2 text-center text-xs font-semibold text-[#1d336b]">
                                Use the top-right button to switch theme
                            </p>
                        </div>
                    </div>
                </aside>

                <section className="space-y-4">
                    <div className="rounded-[1.6rem] border border-slate-200 bg-[#eef4ff] p-4 dark:border-slate-800 dark:bg-slate-900/70">
                        <div className="grid gap-4 lg:grid-cols-[1.4fr_0.95fr]">
                            <div className="rounded-[1.5rem] border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                                <p className="text-[11px] font-semibold uppercase tracking-[0.32em] text-[#5671bb] dark:text-blue-300">
                                    Basketball Analytics Dashboard
                                </p>
                                <h3 className="mt-3 text-4xl font-semibold tracking-tight text-slate-950 dark:text-white">
                                    {content.title}
                                </h3>
                                <p className="mt-3 max-w-2xl text-sm leading-7 text-slate-600 dark:text-slate-300">
                                    {content.description}
                                </p>
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                {stats.map((item) => (
                                    <article
                                        key={item.label}
                                        className="rounded-[1.4rem] border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <p className="text-[10px] font-semibold uppercase tracking-[0.3em] text-[#7c8fbe] dark:text-slate-400">
                                            {item.label}
                                        </p>
                                        <p className="mt-3 text-3xl font-semibold text-slate-950 dark:text-white">
                                            {item.value}
                                        </p>
                                        <p className="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                            {item.note}
                                        </p>
                                    </article>
                                ))}
                            </div>
                        </div>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-[1.25fr_0.75fr]">
                        <article className="rounded-[1.6rem] border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <p className="text-xs font-semibold text-[#6d84b9] dark:text-blue-300">
                                Team performance snapshot
                            </p>
                            <h4 className="mt-2 text-3xl font-semibold text-slate-950 dark:text-white">
                                Win rate by team
                            </h4>

                            <div className="mt-8 space-y-6">
                                {content.bars.map((item) => (
                                    <div key={item.label}>
                                        <div className="mb-2 flex items-end justify-between gap-4">
                                            <div>
                                                <p className="text-sm font-semibold text-slate-900 dark:text-white">
                                                    {item.label}
                                                </p>
                                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                                    {item.sublabel}
                                                </p>
                                            </div>
                                            <p className="text-sm font-semibold text-[#3156c5] dark:text-blue-300">
                                                {item.value}%
                                            </p>
                                        </div>
                                        <div className="h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                                            <div
                                                className="h-2 rounded-full bg-[#3156c5] dark:bg-blue-400"
                                                style={{ width: `${item.value}%` }}
                                            />
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </article>

                        <article className="rounded-[1.6rem] border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <p className="text-xs font-semibold text-[#6d84b9] dark:text-blue-300">
                                Coaching insights
                            </p>
                            <h4 className="mt-2 text-3xl font-semibold text-slate-950 dark:text-white">
                                Priority notes
                            </h4>

                            <div className="mt-8 space-y-4">
                                {content.notes.map((note) => (
                                    <div
                                        key={note}
                                        className="rounded-2xl bg-slate-50 p-4 text-sm leading-6 text-slate-600 dark:bg-slate-800/70 dark:text-slate-300"
                                    >
                                        {note}
                                    </div>
                                ))}
                            </div>
                        </article>
                    </div>

                    <div className="grid gap-4 lg:grid-cols-[1.25fr_0.75fr]">
                        <article className="rounded-[1.6rem] border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <p className="text-xs font-semibold text-[#6d84b9] dark:text-blue-300">
                                Recent activity
                            </p>
                            <h4 className="mt-2 text-3xl font-semibold text-slate-950 dark:text-white">
                                {content.activityTitle}
                            </h4>

                            <div className="mt-8 overflow-hidden rounded-2xl border border-slate-100 dark:border-slate-800">
                                <div className="grid grid-cols-[1.8fr_0.7fr_0.7fr] bg-slate-50 px-4 py-3 text-[11px] font-semibold uppercase tracking-[0.25em] text-slate-400 dark:bg-slate-800/50 dark:text-slate-500">
                                    <span>Matchup</span>
                                    <span>Result</span>
                                    <span>Meta</span>
                                </div>
                                {content.activityRows.map((row) => (
                                    <div
                                        key={row.label}
                                        className="grid grid-cols-[1.8fr_0.7fr_0.7fr] border-t border-slate-100 px-4 py-4 text-sm text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                    >
                                        <span>{row.label}</span>
                                        <span className="font-semibold text-[#3156c5] dark:text-blue-300">
                                            {row.result}
                                        </span>
                                        <span>{row.meta}</span>
                                    </div>
                                ))}
                            </div>
                        </article>

                        <article className="rounded-[1.6rem] border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                            <p className="text-xs font-semibold text-[#6d84b9] dark:text-blue-300">
                                Player leaderboard
                            </p>
                            <h4 className="mt-2 text-3xl font-semibold text-slate-950 dark:text-white">
                                {content.leaderboardTitle}
                            </h4>

                            <div className="mt-8 space-y-4">
                                {content.leaders.map((leader, index) => (
                                    <div
                                        key={leader.name}
                                        className="flex items-center gap-4 rounded-2xl bg-slate-50 px-4 py-4 dark:bg-slate-800/70"
                                    >
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-[#3156c5] dark:bg-blue-500/15 dark:text-blue-300">
                                            {index + 1}
                                        </div>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold text-slate-900 dark:text-white">
                                                {leader.name}
                                            </p>
                                            <p className="text-[11px] uppercase tracking-[0.24em] text-slate-400 dark:text-slate-500">
                                                {leader.team}
                                            </p>
                                        </div>
                                        <p className="text-lg font-semibold text-[#3156c5] dark:text-blue-300">
                                            {leader.value}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </article>
                    </div>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
