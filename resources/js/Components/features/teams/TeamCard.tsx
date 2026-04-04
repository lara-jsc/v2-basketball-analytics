import { type Team } from '@/types';
import { Link } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, XCircle } from 'lucide-react';

interface TeamCardProps {
    team: Team;
}

/**
 * Arena-themed team card for the Teams index page.
 */
export function TeamCard({ team }: TeamCardProps) {
    const logoUrl = team.logo_path ? `/storage/${team.logo_path}` : null;

    return (
        <Link
            href={route('teams.show', { id: team.id })}
            className="group block rounded-xl border border-border bg-card overflow-hidden transition-all hover:border-primary/50 hover:shadow-[0_0_24px_0_rgba(152,0,46,0.12)]"
        >
            {/* Gradient header band */}
            <div className="relative h-14 bg-gradient-to-r from-primary/25 via-primary/10 to-transparent flex items-center px-4 gap-3">
                {/* Court line accent */}
                <div className="pointer-events-none absolute inset-0 bg-[repeating-linear-gradient(90deg,transparent,transparent_40px,rgba(249,160,27,0.03)_40px,rgba(249,160,27,0.03)_41px)]" />

                {/* Logo / initials avatar */}
                <div className="relative flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-primary/20 group-hover:ring-2 group-hover:ring-accent/30 transition-all">
                    {logoUrl ? (
                        <img
                            src={logoUrl}
                            alt={`${team.name} logo`}
                            className="h-full w-full object-cover"
                        />
                    ) : (
                        <span className="font-display text-sm font-bold text-primary">
                            {team.code.slice(0, 3).toUpperCase()}
                        </span>
                    )}
                </div>

                {/* Team code jersey badge */}
                <div className="flex flex-col gap-0.5">
                    <span className="font-display text-base font-bold tracking-wide text-foreground leading-none">
                        {team.name}
                    </span>
                    <span className="inline-flex items-center gap-1">
                        <span className="font-ui text-[10px] font-bold tracking-widest text-accent uppercase border border-accent/30 bg-accent/10 rounded px-1.5 py-0.5">
                            {team.code}
                        </span>
                        {team.is_active ? (
                            <span className="flex items-center gap-0.5 text-[10px] font-ui font-semibold text-emerald-400">
                                <CheckCircle2 size={10} /> Active
                            </span>
                        ) : (
                            <span className="flex items-center gap-0.5 text-[10px] font-ui font-semibold text-muted-foreground">
                                <XCircle size={10} /> Inactive
                            </span>
                        )}
                    </span>
                </div>
            </div>

            {/* Card body */}
            <div className="flex items-center justify-between px-4 py-3">
                <p className="text-xs text-muted-foreground font-ui">
                    Manage players &amp; stats
                </p>
                <span className="flex items-center gap-1 text-[11px] font-ui font-semibold text-primary opacity-60 group-hover:opacity-100 transition-all group-hover:gap-1.5">
                    Open <ArrowRight size={11} />
                </span>
            </div>
        </Link>
    );
}
