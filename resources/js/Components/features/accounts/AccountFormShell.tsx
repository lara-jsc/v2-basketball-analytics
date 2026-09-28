import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { type ReactNode } from 'react';

interface AccountFormShellProps {
    crumb: string;
    title: string;
    description: string;
    children: ReactNode;
}

/** Breadcrumb + card frame shared by the account create/edit pages. */
export function AccountFormShell({ crumb, title, description, children }: AccountFormShellProps) {
    return (
        <div className="flex max-w-3xl flex-col gap-5">
            <nav aria-label="Breadcrumb" className="flex items-center gap-2 text-sm text-muted-foreground">
                <Link
                    href={route('accounts.index')}
                    className="flex items-center gap-1.5 transition-colors hover:text-foreground"
                >
                    <ArrowLeft size={14} />
                    Accounts
                </Link>
                <span aria-hidden="true">/</span>
                <span className="truncate text-foreground">{crumb}</span>
            </nav>

            <div className="overflow-hidden rounded-xl border border-border bg-card">
                <div className="border-b border-border px-5 py-4">
                    <h1 className="font-display text-lg font-bold uppercase tracking-widest text-foreground">{title}</h1>
                    <p className="mt-0.5 font-ui text-sm text-muted-foreground">{description}</p>
                </div>
                {children}
            </div>
        </div>
    );
}
