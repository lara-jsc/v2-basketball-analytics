import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { type AccountSummary, type PageProps, type UserRole } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Pencil, Plus, ShieldCheck } from 'lucide-react';
import { useMemo, useState } from 'react';

interface AccountsIndexProps extends PageProps {
    accounts: AccountSummary[];
}

type RoleFilter = 'all' | UserRole;

const FILTERS: { value: RoleFilter; label: string }[] = [
    { value: 'all', label: 'All' },
    { value: 'admin', label: 'Admins' },
    { value: 'coach', label: 'Coaches' },
];

export default function AccountsIndex({ accounts }: AccountsIndexProps) {
    const { flash, auth } = usePage<AccountsIndexProps>().props;
    const [filter, setFilter] = useState<RoleFilter>('all');

    const counts = useMemo(
        () => ({
            all: accounts.length,
            admin: accounts.filter((account) => account.role === 'admin').length,
            coach: accounts.filter((account) => account.role === 'coach').length,
        }),
        [accounts],
    );

    const visible = filter === 'all' ? accounts : accounts.filter((account) => account.role === filter);

    return (
        <AuthenticatedLayout>
            <Head title="Accounts" />

            <div className="flex flex-col gap-5">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="font-display text-xl font-black uppercase tracking-[2px] text-foreground">
                            Accounts
                        </h1>
                        <p className="mt-1 font-ui text-sm font-semibold text-muted-foreground">
                            Create sign-ins for coaches and admins, and move coaches between teams.
                        </p>
                    </div>
                    <Link
                        href={route('accounts.create')}
                        className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 font-ui text-sm font-semibold tracking-wide text-primary-foreground transition-[opacity,box-shadow] duration-200 hover:opacity-90 hover:shadow-[0_0_12px_rgba(152,0,46,0.4)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        <Plus size={14} />
                        New account
                    </Link>
                </div>

                {flash?.success && (
                    <div
                        role="status"
                        className="rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 font-ui text-sm font-semibold text-emerald-700 dark:text-emerald-400"
                    >
                        {flash.success}
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border border-border bg-card">
                    <div className="flex items-center justify-between gap-3 border-b border-border px-4 py-3">
                        <div role="tablist" aria-label="Filter by role" className="inline-flex rounded-lg bg-muted/60 p-1">
                            {FILTERS.map((option) => {
                                const active = option.value === filter;

                                return (
                                    <button
                                        key={option.value}
                                        type="button"
                                        role="tab"
                                        aria-selected={active}
                                        onClick={() => setFilter(option.value)}
                                        className={`flex items-center gap-1.5 rounded-md px-3 py-1.5 font-ui text-xs font-semibold tracking-wide transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring ${
                                            active
                                                ? 'bg-card text-foreground shadow-sm'
                                                : 'text-muted-foreground hover:text-foreground'
                                        }`}
                                    >
                                        {option.label}
                                        <span className="tabular-nums text-muted-foreground">{counts[option.value]}</span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {visible.length === 0 ? (
                        <div className="px-6 py-12 text-center">
                            <p className="font-ui text-sm font-semibold text-foreground">
                                No {filter === 'admin' ? 'admin' : 'coach'} accounts yet
                            </p>
                            <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                                {filter === 'coach'
                                    ? 'Create a coach account and pick their team to make them available for staffing.'
                                    : 'Promote a coach from their edit page, or create a new admin account.'}
                            </p>
                        </div>
                    ) : (
                        <>
                            {/* Tablet and up: table */}
                            <table className="hidden w-full text-sm md:table">
                                <thead>
                                    <tr className="border-b border-border text-left">
                                        <Th>Name</Th>
                                        <Th>Role</Th>
                                        <Th>Team</Th>
                                        <Th>Staffing</Th>
                                        <th className="w-px px-4 py-2.5">
                                            <span className="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {visible.map((account) => (
                                        <tr
                                            key={account.id}
                                            className="border-b border-border transition-colors last:border-0 hover:bg-muted/30"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2 font-semibold text-foreground">
                                                    {account.name}
                                                    {account.id === auth.user?.id && <YouTag />}
                                                </div>
                                                <div className="text-xs text-muted-foreground">{account.email}</div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <RoleBadge role={account.role} />
                                            </td>
                                            <td className="px-4 py-3 text-foreground">
                                                {account.team?.name ?? <span className="text-muted-foreground">—</span>}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StaffingLabel account={account} />
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <EditLink account={account} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {/* Phone: stacked list */}
                            <ul className="divide-y divide-border md:hidden">
                                {visible.map((account) => (
                                    <li key={account.id} className="flex items-start justify-between gap-3 px-4 py-3">
                                        <div className="min-w-0 space-y-1">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-semibold text-foreground">{account.name}</span>
                                                {account.id === auth.user?.id && <YouTag />}
                                                <RoleBadge role={account.role} />
                                            </div>
                                            <div className="truncate text-xs text-muted-foreground">{account.email}</div>
                                            {account.role === 'coach' && (
                                                <div className="text-xs text-foreground">
                                                    {account.team?.name ?? 'No team'}
                                                    {account.staffing && (
                                                        <>
                                                            {' · '}
                                                            <StaffingLabel account={account} />
                                                        </>
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                        <EditLink account={account} />
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Th({ children }: { children: string }) {
    return (
        <th className="px-4 py-2.5 font-ui text-xs font-semibold uppercase tracking-wide text-muted-foreground">
            {children}
        </th>
    );
}

function RoleBadge({ role }: { role: UserRole }) {
    if (role === 'admin') {
        return (
            <span className="inline-flex items-center gap-1 rounded-full border border-primary/30 bg-primary/10 px-2 py-0.5 text-xs font-semibold text-primary dark:text-foreground">
                <ShieldCheck size={12} />
                Admin
            </span>
        );
    }

    return (
        <span className="inline-flex items-center rounded-full border border-border px-2 py-0.5 text-xs font-semibold text-muted-foreground">
            Coach
        </span>
    );
}

function StaffingLabel({ account }: { account: AccountSummary }) {
    if (account.staffing === 'main') {
        return <span className="font-semibold text-foreground">Main coach</span>;
    }

    if (account.staffing === 'assistant') {
        return <span className="text-foreground">Assistant coach</span>;
    }

    return <span className="text-muted-foreground">—</span>;
}

function YouTag() {
    return <span className="rounded bg-muted px-1.5 py-0.5 text-[11px] font-semibold text-muted-foreground">You</span>;
}

function EditLink({ account }: { account: AccountSummary }) {
    return (
        <Link
            href={route('accounts.edit', account.id)}
            aria-label={`Edit ${account.name}`}
            className="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 font-ui text-xs font-semibold tracking-wide text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        >
            <Pencil size={12} />
            Edit
        </Link>
    );
}
