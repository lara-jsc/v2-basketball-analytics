import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { type PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ArrowRight, FileUp, Users2, Swords } from 'lucide-react';

/**
 * Home screen — entry point after login.
 * Shows the three in-scope feature areas with navigation links.
 */
export default function Dashboard({ auth }: PageProps) {
  return (
    <AuthenticatedLayout
      header={
        <div>
          <p className="text-xs uppercase tracking-widest text-muted-foreground">Welcome back</p>
          <h2 className="text-lg font-semibold text-foreground">{auth.user.name}</h2>
        </div>
      }
    >
      <Head title="Dashboard" />

      <div className="space-y-8">
        {/* Page headline */}
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-foreground md:text-3xl">
            HoopSense<span className="text-primary">+</span>
          </h1>
          <p className="mt-1 text-muted-foreground">
            Pre-game decision support powered by plus-minus analytics.
          </p>
        </div>

        {/* Feature cards — tablet-first 2-col grid */}
        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          <FeatureCard
            href={route('teams.index')}
            icon={<Users2 size={22} />}
            title="Team & Player Management"
            description="Create teams, upload rosters via CSV, and manage individual player records and stats."
          />
          <FeatureCard
            href={route('teams.index')}
            icon={<FileUp size={22} />}
            title="CSV Roster Upload"
            description="Bulk-import player stats from a structured CSV file. Download the template to get started."
          />
          <FeatureCard
            href={route('comparison.index')}
            icon={<Swords size={22} />}
            title="Team Comparison"
            description="Compare two teams side-by-side with win probability, win rate, and lineup recommendations."
          />
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

// ── Internal component ──────────────────────────────────────────────────────

interface FeatureCardProps {
  href: string;
  icon: React.ReactNode;
  title: string;
  description: string;
}

function FeatureCard({ href, icon, title, description }: FeatureCardProps) {
  return (
    <Link
      href={href}
      className="group flex flex-col gap-3 rounded-xl border border-border bg-card p-5 transition-colors hover:border-primary/50 hover:bg-card/80"
    >
      <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary/10 text-primary">
        {icon}
      </div>
      <div className="flex-1">
        <h3 className="font-semibold text-foreground">{title}</h3>
        <p className="mt-1 text-sm text-muted-foreground">{description}</p>
      </div>
      <div className="flex items-center gap-1 text-xs font-medium text-primary opacity-0 transition-opacity group-hover:opacity-100">
        Get started <ArrowRight size={12} />
      </div>
    </Link>
  );
}
