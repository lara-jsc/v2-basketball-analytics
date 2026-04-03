import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader } from '@/Components/ui/card';
import { type Team } from '@/types';
import { Link } from '@inertiajs/react';
import { ChevronRight, Users2 } from 'lucide-react';

interface TeamCardProps {
    team: Team;
}

/**
 * Compact team summary card used on the Teams index page.
 * Shows team logo if available, otherwise falls back to a code-based avatar.
 */
export function TeamCard({ team }: TeamCardProps) {
    const logoUrl = team.logo_path ? `/storage/${team.logo_path}` : null;

    return (
        <Link href={route('teams.show', { id: team.id })} className="block group">
            <Card className="h-full transition-colors hover:border-primary/50">
                <CardHeader className="pb-3">
                    <div className="flex items-start justify-between gap-2">
                        <div className="flex items-center gap-3">
                            {/* Team logo — falls back to code initials */}
                            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg overflow-hidden border border-border bg-primary/10">
                                {logoUrl ? (
                                    <img
                                        src={logoUrl}
                                        alt={`${team.name} logo`}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    <span className="text-sm font-bold text-primary">
                                        {team.code.slice(0, 3).toUpperCase()}
                                    </span>
                                )}
                            </div>
                            <div>
                                <p className="font-semibold leading-tight text-foreground">{team.name}</p>
                                <p className="text-xs text-muted-foreground">{team.code}</p>
                            </div>
                        </div>
                        <ChevronRight
                            size={16}
                            className="mt-1 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
                        />
                    </div>
                </CardHeader>
                <CardContent className="pt-0">
                    <div className="flex items-center gap-2">
                        <Users2 size={13} className="text-muted-foreground" />
                        <span className="text-xs text-muted-foreground">Manage players &amp; stats</span>
                        {!team.is_active && (
                            <Badge variant="secondary" className="ml-auto text-xs">Inactive</Badge>
                        )}
                    </div>
                </CardContent>
            </Card>
        </Link>
    );
}
