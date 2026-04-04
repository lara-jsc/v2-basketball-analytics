import { type Team } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronRight, Users2 } from 'lucide-react';

interface TeamCardProps {
    team: Team;
}

/**
 * Compact team summary card used on the Teams index page.
 * Arena-themed: crimson hover glow, logo avatar with fallback initials.
 */
export function TeamCard({ team }: TeamCardProps) {
    const logoUrl = team.logo_path ? `/storage/${team.logo_path}` : null;

    return (
        <Link
            href={route('teams.show', { id: team.id })}
            className="group block rounded-xl border border-border bg-card p-4 transition-all hover:border-primary/40 hover:shadow-[0_0_20px_0_rgba(152,0,46,0.1)]"
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    {/* Logo / initials avatar */}
                    <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-border bg-primary/10">
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

                    <div>
                        <p className="font-display text-base font-bold tracking-wide text-foreground">
                            {team.name}
                        </p>
                        <p className="font-ui text-xs font-medium tracking-widest text-muted-foreground uppercase">
                            {team.code}
                            {!team.is_active && (
                                <span className="ml-2 rounded px-1.5 py-0.5 bg-muted text-muted-foreground text-[10px]">
                                    Inactive
                                </span>
                            )}
                        </p>
                    </div>
                </div>

                <ChevronRight
                    size={16}
                    className="shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary"
                />
            </div>

            <div className="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground">
                <Users2 size={12} />
                <span>Manage players &amp; stats</span>
            </div>
        </Link>
    );
}
