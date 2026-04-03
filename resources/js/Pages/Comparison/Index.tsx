import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { type PageProps, type Team } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { GitCompare } from 'lucide-react';

interface ComparisonIndexProps extends PageProps {
  teams: Team[];
}

/**
 * Team selector page — choose two active teams to compare.
 * Submits POST /comparison which redirects to /comparison/{teamA}/{teamB}.
 */
export default function ComparisonIndex({ teams }: ComparisonIndexProps) {
  const { data, setData, post, processing, errors } = useForm({
    team_a: '',
    team_b: '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post(route('comparison.select'));
  }

  const activeTeams = teams.filter((t) => t.is_active);

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center gap-2">
          <GitCompare size={18} className="text-muted-foreground" />
          <h2 className="text-lg font-semibold text-foreground">Team Comparison</h2>
        </div>
      }
    >
      <Head title="Compare Teams" />

      <div className="mx-auto max-w-lg px-4 py-10">
        <div className="rounded-2xl border border-border bg-card p-6 shadow-sm space-y-6">
          <div className="space-y-1">
            <h3 className="text-base font-semibold text-foreground">Select two teams</h3>
            <p className="text-sm text-muted-foreground">
              Choose a home team and an opponent to generate a pre-game analysis.
            </p>
          </div>

          {activeTeams.length < 2 ? (
            <p className="rounded-lg bg-muted px-4 py-3 text-sm text-muted-foreground">
              You need at least two active teams to run a comparison.
            </p>
          ) : (
            <form onSubmit={submit} className="space-y-4">
              <TeamSelect
                label="Home Team"
                id="team_a"
                value={data.team_a}
                onChange={(v) => setData('team_a', v)}
                teams={activeTeams}
                exclude={data.team_b}
                error={errors.team_a}
              />

              <TeamSelect
                label="Opponent"
                id="team_b"
                value={data.team_b}
                onChange={(v) => setData('team_b', v)}
                teams={activeTeams}
                exclude={data.team_a}
                error={errors.team_b}
              />

              <Button
                type="submit"
                className="w-full"
                disabled={!data.team_a || !data.team_b || processing}
              >
                <GitCompare size={15} className="mr-2" />
                Compare Teams
              </Button>
            </form>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

// ── Internal helpers ─────────────────────────────────────────────────────────

function TeamSelect({
  label,
  id,
  value,
  onChange,
  teams,
  exclude,
  error,
}: {
  label: string;
  id: string;
  value: string;
  onChange: (v: string) => void;
  teams: Team[];
  exclude: string;
  error?: string;
}) {
  return (
    <div className="space-y-1.5">
      <label htmlFor={id} className="text-sm font-medium text-foreground">
        {label}
      </label>
      <select
        id={id}
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground shadow-sm focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-1"
      >
        <option value="">Select a team…</option>
        {teams
          .filter((t) => String(t.id) !== exclude)
          .map((t) => (
            <option key={t.id} value={String(t.id)}>
              {t.name} ({t.code})
            </option>
          ))}
      </select>
      {error && <p className="text-xs text-destructive">{error}</p>}
    </div>
  );
}
