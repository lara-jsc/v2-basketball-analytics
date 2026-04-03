import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CsvUploadForm } from '@/Components/features/csv/CsvUploadForm';
import { ImportStatus } from '@/Components/features/csv/ImportStatus';
import { PlayerFormSheet } from '@/Components/features/players/PlayerFormSheet';
import { PlayersTable } from '@/Components/features/players/PlayersTable';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { type CsvImport, type PageProps, type PlayerWithStats, type Team } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, Pencil, Plus, Upload } from 'lucide-react';
import { useState } from 'react';

interface TeamsShowProps extends PageProps {
    team: Team;
    players: PlayerWithStats[];
    latestImport: CsvImport | null;
}

/**
 * Team detail page — shows:
 *   1. Team header (code, name, status, edit link)
 *   2. CSV upload form (collapsible)
 *   3. Latest import status with polling
 *   4. Players stats table with CRUD actions
 */
export default function TeamsShow({ team, players, latestImport }: TeamsShowProps) {
    const { flash } = usePage<TeamsShowProps>().props;
    const [showUpload, setShowUpload] = useState(players.length === 0);
    const [addPlayerOpen, setAddPlayerOpen] = useState(false);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link href={route('teams.index')}>
                            <Button variant="ghost" size="sm" className="gap-1.5 text-muted-foreground">
                                <ArrowLeft size={14} />
                                Teams
                            </Button>
                        </Link>
                        <span className="text-muted-foreground">/</span>
                        <div className="flex items-center gap-2">
                            <h2 className="text-lg font-semibold text-foreground">{team.name}</h2>
                            <Badge variant="outline" className="text-xs font-mono">
                                {team.code}
                            </Badge>
                            {!team.is_active && (
                                <Badge variant="secondary" className="text-xs">Inactive</Badge>
                            )}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href={route('teams.edit', { id: team.id })}>
                            <Button variant="ghost" size="sm" className="gap-1.5 text-muted-foreground">
                                <Pencil size={14} />
                                Edit Team
                            </Button>
                        </Link>
                        <Button
                            variant="outline"
                            size="sm"
                            className="gap-2"
                            onClick={() => setShowUpload((v) => !v)}
                        >
                            <Upload size={14} />
                            {showUpload ? 'Hide Upload' : 'Upload CSV'}
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title={team.name} />

            {flash?.success && (
                <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-400">
                    {flash.success}
                </div>
            )}

            <div className="space-y-5">
                {/* CSV upload form — shown by default when no players yet */}
                {showUpload && <CsvUploadForm teamId={team.id} />}

                {/* Import status with polling */}
                <ImportStatus latestImport={latestImport} />

                {/* Players stats table */}
                <div>
                    <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-semibold text-foreground">
                            Players
                            {players.length > 0 && (
                                <span className="ml-2 text-sm font-normal text-muted-foreground">
                                    ({players.length})
                                </span>
                            )}
                        </h3>
                        <Button
                            size="sm"
                            className="gap-2"
                            onClick={() => setAddPlayerOpen(true)}
                        >
                            <Plus size={14} />
                            Add Player
                        </Button>
                    </div>
                    <PlayersTable players={players} team={team} />
                </div>
            </div>

            {/* Add player drawer */}
            <PlayerFormSheet
                open={addPlayerOpen}
                onOpenChange={setAddPlayerOpen}
                team={team}
            />
        </AuthenticatedLayout>
    );
}
