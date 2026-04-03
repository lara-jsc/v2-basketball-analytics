import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { TeamCard } from '@/Components/features/teams/TeamCard';
import { Button } from '@/Components/ui/button';
import { type PageProps, type Team } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';

interface TeamsIndexProps extends PageProps {
  teams: Team[];
}

/**
 * Teams index — list all teams with navigation to each team's management page.
 */
export default function TeamsIndex({ teams }: TeamsIndexProps) {
  const { flash } = usePage<TeamsIndexProps>().props;

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold text-foreground">Teams</h2>
          <Link href={route('teams.create')}>
            <Button size="sm" className="gap-2">
              <Plus size={14} />
              New Team
            </Button>
          </Link>
        </div>
      }
    >
      <Head title="Teams" />

      {flash?.success && (
        <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-800 dark:bg-green-950 dark:text-green-400">
          {flash.success}
        </div>
      )}

      {teams.length === 0 ? (
        /* Empty state */
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card py-16 text-center">
          <p className="text-sm font-medium text-muted-foreground">No teams yet</p>
          <p className="mt-1 text-xs text-muted-foreground">
            Create a team to start uploading rosters.
          </p>
          <Link href={route('teams.create')} className="mt-4">
            <Button size="sm" className="gap-2">
              <Plus size={14} />
              Create your first team
            </Button>
          </Link>
        </div>
      ) : (
        /* Team grid — 2 columns on tablet */
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {teams.map((team) => (
            <TeamCard key={team.id} team={team} />
          ))}
        </div>
      )}
    </AuthenticatedLayout>
  );
}
