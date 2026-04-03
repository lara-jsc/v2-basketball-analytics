import { type Team, type WinProbabilityResult } from '@/types';
import { Loader2 } from 'lucide-react';

interface WinProbabilityBarProps {
  teamA: Team;
  teamB: Team;
  result: WinProbabilityResult | null;
}

/**
 * Visual win probability and win rate display.
 * Shows a split bar with percentages. Renders a loading state while the Job is pending.
 * Null result = job dispatched but not yet complete.
 */
export function WinProbabilityBar({ teamA, teamB, result }: WinProbabilityBarProps) {
  if (result === null) {
    return (
      <div className="flex items-center gap-2 rounded-xl border border-border bg-card px-5 py-4 text-sm text-muted-foreground">
        <Loader2 size={14} className="animate-spin" />
        Computing win probability…
      </div>
    );
  }

  const probA = Math.round(result.team_a_win_probability * 100);
  const probB = Math.round(result.team_b_win_probability * 100);
  const rateA = Math.round(result.team_a_win_rate * 100);
  const rateB = Math.round(result.team_b_win_rate * 100);
  const aLeads = probA >= probB;

  return (
    <div className="rounded-xl border border-border bg-card p-5 space-y-4">
      <h4 className="text-sm font-semibold text-foreground">Win Probability</h4>

      {/* Split bar */}
      <div className="space-y-1.5">
        <div className="flex overflow-hidden rounded-full h-4">
          <div
            className="bg-primary transition-all"
            style={{ width: `${probA}%` }}
          />
          <div
            className="bg-muted transition-all"
            style={{ width: `${probB}%` }}
          />
        </div>
        <div className="flex justify-between text-xs">
          <span className={`font-semibold ${aLeads ? 'text-primary' : 'text-muted-foreground'}`}>
            {teamA.name} {probA}%
          </span>
          <span className={`font-semibold ${!aLeads ? 'text-primary' : 'text-muted-foreground'}`}>
            {probB}% {teamB.name}
          </span>
        </div>
      </div>

      {/* Win rate */}
      <div className="flex justify-between border-t border-border pt-3 text-xs">
        <div>
          <p className="text-muted-foreground">Win Rate</p>
          <p className={`mt-0.5 font-semibold ${rateA >= rateB ? 'text-accent' : 'text-foreground'}`}>
            {teamA.name}: {rateA}%
          </p>
        </div>
        <div className="text-right">
          <p className="text-muted-foreground">Win Rate</p>
          <p className={`mt-0.5 font-semibold ${rateB >= rateA ? 'text-accent' : 'text-foreground'}`}>
            {teamB.name}: {rateB}%
          </p>
        </div>
      </div>
    </div>
  );
}
