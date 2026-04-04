import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { TeamStatsPanel } from '@/Components/features/comparison/TeamStatsPanel';
import { WinProbabilityBar } from '@/Components/features/comparison/WinProbabilityBar';
import { PlayerMatchupTable } from '@/Components/features/comparison/PlayerMatchupTable';
import { LineupModal } from '@/Components/features/lineup/LineupModal';
import { Button } from '@/Components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import {
  type LineupRecommendation,
  type PageProps,
  type PlayerMatchupResult,
  type PlayerWithStats,
  type Team,
  type TeamAggregateStats,
  type WinProbabilityResult,
} from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, GitCompare, Users } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface ComparisonShowProps extends PageProps {
  teamA: Team;
  teamB: Team;
  playersA: PlayerWithStats[];
  playersB: PlayerWithStats[];
  teamAStats: TeamAggregateStats;
  teamBStats: TeamAggregateStats;
  teamAPlusMinus: number | null;
  teamBPlusMinus: number | null;
  winProbability: WinProbabilityResult | null;
  lineup: LineupRecommendation | null;
  matchup: PlayerMatchupResult | null;
  selectedAId: number | null;
  selectedBId: number | null;
}

const POLL_INTERVAL = 3000;

/**
 * Main comparison page.
 *
 * Tab 1 — Team Stats: side-by-side aggregate stats + win probability bar.
 *   - Lineup modal triggered by "View Lineup" button.
 *   - Polls for winProbability and lineup while null.
 *
 * Tab 2 — Player Matchup: two player selectors + stat comparison table.
 *   - Polls for matchup result while isPendingMatchup.
 */
export default function ComparisonShow({
  teamA,
  teamB,
  playersA,
  playersB,
  teamAStats,
  teamBStats,
  teamAPlusMinus,
  teamBPlusMinus,
  winProbability,
  lineup,
  matchup,
  selectedAId,
  selectedBId,
}: ComparisonShowProps) {
  const [lineupOpen, setLineupOpen] = useState(false);
  const [localPlayerA, setLocalPlayerA] = useState<string>(
    selectedAId ? String(selectedAId) : '',
  );
  const [localPlayerB, setLocalPlayerB] = useState<string>(
    selectedBId ? String(selectedBId) : '',
  );

  // ── Polling for win probability + lineup ──────────────────────────────────
  const statsNeedPoll = winProbability === null || lineup === null;
  const statsTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    if (!statsNeedPoll) return;
    statsTimerRef.current = setInterval(() => {
      router.reload({ only: ['winProbability', 'lineup'] });
    }, POLL_INTERVAL);
    return () => {
      if (statsTimerRef.current) clearInterval(statsTimerRef.current);
    };
  }, [statsNeedPoll]);

  // ── Polling for player matchup ─────────────────────────────────────────────
  const isPendingMatchup =
    selectedAId !== null && selectedBId !== null && matchup === null;
  const matchupTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

  useEffect(() => {
    if (!isPendingMatchup) return;
    matchupTimerRef.current = setInterval(() => {
      router.reload({ only: ['matchup'] });
    }, POLL_INTERVAL);
    return () => {
      if (matchupTimerRef.current) clearInterval(matchupTimerRef.current);
    };
  }, [isPendingMatchup]);

  // ── Player matchup selection ──────────────────────────────────────────────
  const dispatchMatchup = useCallback(
    (aId: string, bId: string) => {
      if (!aId || !bId) return;
      router.get(
        route('comparison.show', [teamA.id, teamB.id]),
        { player_a: aId, player_b: bId },
        { preserveScroll: true, preserveState: true },
      );
    },
    [teamA.id, teamB.id],
  );

  function handlePlayerAChange(v: string) {
    setLocalPlayerA(v);
    if (v && localPlayerB) dispatchMatchup(v, localPlayerB);
  }

  function handlePlayerBChange(v: string) {
    setLocalPlayerB(v);
    if (localPlayerA && v) dispatchMatchup(localPlayerA, v);
  }

  const selectedPlayerA = playersA.find((p) => String(p.id) === localPlayerA) ?? null;
  const selectedPlayerB = playersB.find((p) => String(p.id) === localPlayerB) ?? null;

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-center gap-3">
            <Link
              href={route('comparison.index')}
              className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors font-ui font-semibold"
            >
              <ArrowLeft size={14} />
              Back
            </Link>
            <span className="text-border">/</span>
            <div className="flex items-center gap-2">
              <GitCompare size={15} className="text-accent" />
              <span className="font-display text-sm font-bold tracking-wide text-foreground uppercase">
                {teamA.name}
              </span>
              <span className="rounded border border-primary/40 bg-primary/10 px-2 py-0.5 font-display text-[10px] font-bold text-primary tracking-widest uppercase">
                VS
              </span>
              <span className="font-display text-sm font-bold tracking-wide text-foreground uppercase">
                {teamB.name}
              </span>
            </div>
          </div>
        </div>
      }
    >
      <Head title={`${teamA.name} vs ${teamB.name}`} />

      <div className="px-2 py-4 space-y-4">
        <Tabs defaultValue="stats">
          <TabsList className="w-full">
            <TabsTrigger value="stats" className="flex-1">
              Team Stats
            </TabsTrigger>
            <TabsTrigger value="matchup" className="flex-1">
              Player Matchup
            </TabsTrigger>
          </TabsList>

          {/* ── Tab 1: Team Stats ───────────────────────────────────────── */}
          <TabsContent value="stats" className="space-y-4 pt-4">
            <WinProbabilityBar teamA={teamA} teamB={teamB} result={winProbability} />

            <TeamStatsPanel
              teamA={teamA}
              teamB={teamB}
              statsA={teamAStats}
              statsB={teamBStats}
              plusMinusA={teamAPlusMinus}
              plusMinusB={teamBPlusMinus}
            />

            <div className="flex justify-end">
              <button
                onClick={() => setLineupOpen(true)}
                className="flex items-center gap-2 rounded-lg bg-accent px-4 py-2 text-sm font-ui font-semibold tracking-wide text-accent-foreground transition-all hover:opacity-90 hover:shadow-[0_0_12px_rgba(249,160,27,0.4)]"
              >
                <Users size={14} />
                View Recommended Lineup
              </button>
            </div>
          </TabsContent>

          {/* ── Tab 2: Player Matchup ───────────────────────────────────── */}
          <TabsContent value="matchup" className="space-y-4 pt-4">
            {/* Player selectors */}
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-1.5">
                <label className="text-xs font-medium text-muted-foreground uppercase tracking-widest">
                  {teamA.name}
                </label>
                <select
                  value={localPlayerA}
                  onChange={(e) => handlePlayerAChange(e.target.value)}
                  className="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground font-ui transition-colors focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent/40"
                >
                  <option value="">Select player…</option>
                  {playersA.map((p) => (
                    <option key={p.id} value={String(p.id)}>
                      #{p.jersey_number} {p.first_name} {p.last_name}
                    </option>
                  ))}
                </select>
              </div>

              <div className="space-y-1.5">
                <label className="text-xs font-medium text-muted-foreground uppercase tracking-widest">
                  {teamB.name}
                </label>
                <select
                  value={localPlayerB}
                  onChange={(e) => handlePlayerBChange(e.target.value)}
                  className="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-sm text-foreground font-ui transition-colors focus:outline-none focus:ring-2 focus:ring-accent/40 focus:border-accent/40"
                >
                  <option value="">Select player…</option>
                  {playersB.map((p) => (
                    <option key={p.id} value={String(p.id)}>
                      #{p.jersey_number} {p.first_name} {p.last_name}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            {/* Matchup table or empty state */}
            {selectedPlayerA && selectedPlayerB ? (
              <PlayerMatchupTable
                playerA={selectedPlayerA}
                playerB={selectedPlayerB}
                matchup={matchup}
                isPending={isPendingMatchup}
              />
            ) : (
              <div className="rounded-xl border border-border bg-muted/30 px-5 py-10 text-center text-sm text-muted-foreground">
                Select one player from each team to see the matchup breakdown.
              </div>
            )}
          </TabsContent>
        </Tabs>
      </div>

      <LineupModal
        open={lineupOpen}
        onClose={() => setLineupOpen(false)}
        lineup={lineup}
        teamName={teamA.name}
        players={playersA}
      />
    </AuthenticatedLayout>
  );
}
