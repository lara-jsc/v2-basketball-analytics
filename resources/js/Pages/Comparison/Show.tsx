import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { TeamStatsPanel } from '@/Components/features/comparison/TeamStatsPanel';
import { WinProbabilityBar } from '@/Components/features/comparison/WinProbabilityBar';
import { PlayerMatchupTable } from '@/Components/features/comparison/PlayerMatchupTable';
import { LineupModal } from '@/Components/features/lineup/LineupModal';
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
import { ArrowLeft, BarChart2, Swords, Users } from 'lucide-react';
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

  // ── Polling ──────────────────────────────────────────────────────────────
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

  const isPendingMatchup = selectedAId !== null && selectedBId !== null && matchup === null;
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

  const logoA = teamA.logo_path ? `/storage/${teamA.logo_path}` : null;
  const logoB = teamB.logo_path ? `/storage/${teamB.logo_path}` : null;

  return (
    <AuthenticatedLayout>
      <Head title={`${teamA.name} vs ${teamB.name}`} />

      <div className="flex flex-col gap-5">

        {/* ── Pre-game hero banner ─────────────────────────────────────────── */}
        <div className="relative rounded-2xl overflow-hidden"
             style={{ background: 'rgba(8,12,24,0.95)', border: '1px solid rgba(255,255,255,0.07)' }}>

          {/* Arena background image */}
          <img
            src="/images/dashboard-assets/game-overview.png"
            alt=""
            className="absolute inset-0 w-full h-full object-cover object-center pointer-events-none"
            style={{ filter: 'brightness(0.15) saturate(0.5)', zIndex: 0 }}
          />
          {/* Gradient overlays */}
          <div className="absolute inset-0 pointer-events-none" style={{ zIndex: 1, background: 'linear-gradient(180deg, rgba(8,12,24,0.5) 0%, rgba(8,12,24,0.85) 100%)' }} />
          <div className="absolute inset-0 pointer-events-none" style={{ zIndex: 1, background: 'radial-gradient(ellipse at center, rgba(249,160,27,0.04), transparent 70%)' }} />

          {/* Back link */}
          <div className="relative z-10 px-5 pt-4">
            <Link href={route('comparison.index')}
                  className="inline-flex items-center gap-1.5 text-sm font-semibold transition-colors"
                  style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.4)', letterSpacing: '0.5px' }}>
              <ArrowLeft size={14} />
              Back to Matchup Select
            </Link>
          </div>

          {/* Team matchup layout */}
          <div className="relative z-10 flex items-center justify-center gap-0 px-8 py-8">

            {/* Team A */}
            <div className="flex flex-1 flex-col items-center gap-3">
              {/* Logo */}
              <div className="flex h-24 w-24 items-center justify-center rounded-2xl overflow-hidden"
                   style={{
                     background: logoA ? 'transparent' : 'linear-gradient(135deg, rgba(152,0,46,0.5), rgba(80,0,20,0.7))',
                     border: '2px solid rgba(255,140,0,0.25)',
                     boxShadow: '0 0 40px rgba(152,0,46,0.3), 0 0 80px rgba(152,0,46,0.1)',
                   }}>
                {logoA ? (
                  <img src={logoA} alt={teamA.name} className="h-full w-full object-cover" />
                ) : (
                  <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '22px', fontWeight: 900, color: 'rgba(255,200,200,0.9)' }}>
                    {teamA.code.slice(0, 3)}
                  </span>
                )}
              </div>
              {/* Team info */}
              <div className="text-center">
                <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '13px', fontWeight: 700, color: 'rgba(255,255,255,0.9)', letterSpacing: '1px', textTransform: 'uppercase', textShadow: '0 0 16px rgba(152,0,46,0.5)' }}>
                  {teamA.name}
                </p>
                <p className="mt-1" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '11px', fontWeight: 700, color: 'rgba(152,0,46,0.9)', letterSpacing: '2px', textTransform: 'uppercase' }}>
                  HOME
                </p>
              </div>
              {/* Win probability if computed */}
              {winProbability && (
                <div className="rounded-xl px-4 py-2 text-center"
                     style={{ background: 'rgba(152,0,46,0.15)', border: '1px solid rgba(152,0,46,0.3)' }}>
                  <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '24px', fontWeight: 900, color: '#fff', textShadow: '0 0 20px rgba(255,255,255,0.3)' }}>
                    {Math.round(winProbability.team_a_win_probability * 100)}%
                  </p>
                  <p style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '10px', fontWeight: 700, color: 'rgba(255,255,255,0.4)', letterSpacing: '1px', textTransform: 'uppercase' }}>
                    Win Prob.
                  </p>
                </div>
              )}
            </div>

            {/* VS center */}
            <div className="flex flex-col items-center gap-2 shrink-0 mx-6">
              <div className="flex h-20 w-20 items-center justify-center rounded-full"
                   style={{
                     background: 'radial-gradient(circle, rgba(249,160,27,0.15), rgba(0,0,0,0.6))',
                     border: '2px solid rgba(249,160,27,0.35)',
                     boxShadow: '0 0 40px rgba(249,160,27,0.2)',
                   }}>
                <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '18px', fontWeight: 900, color: '#F9A01B', letterSpacing: '1px', textShadow: '0 0 12px rgba(249,160,27,0.6)' }}>VS</span>
              </div>
              {statsNeedPoll && (
                <div className="flex items-center gap-1.5 rounded-full px-3 py-1"
                     style={{ background: 'rgba(249,160,27,0.08)', border: '1px solid rgba(249,160,27,0.2)' }}>
                  <span className="h-1.5 w-1.5 rounded-full bg-[#F9A01B] animate-pulse" />
                  <span style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '10px', fontWeight: 700, color: 'rgba(249,160,27,0.8)', letterSpacing: '1px', textTransform: 'uppercase' }}>
                    Computing…
                  </span>
                </div>
              )}
            </div>

            {/* Team B */}
            <div className="flex flex-1 flex-col items-center gap-3">
              <div className="flex h-24 w-24 items-center justify-center rounded-2xl overflow-hidden"
                   style={{
                     background: logoB ? 'transparent' : 'linear-gradient(135deg, rgba(30,60,120,0.5), rgba(10,25,60,0.7))',
                     border: '2px solid rgba(255,140,0,0.25)',
                     boxShadow: '0 0 40px rgba(30,80,180,0.3), 0 0 80px rgba(30,60,120,0.1)',
                   }}>
                {logoB ? (
                  <img src={logoB} alt={teamB.name} className="h-full w-full object-cover" />
                ) : (
                  <span style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '22px', fontWeight: 900, color: 'rgba(180,200,255,0.9)' }}>
                    {teamB.code.slice(0, 3)}
                  </span>
                )}
              </div>
              <div className="text-center">
                <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '13px', fontWeight: 700, color: 'rgba(255,255,255,0.9)', letterSpacing: '1px', textTransform: 'uppercase', textShadow: '0 0 16px rgba(30,80,180,0.5)' }}>
                  {teamB.name}
                </p>
                <p className="mt-1" style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '11px', fontWeight: 700, color: 'rgba(100,150,255,0.8)', letterSpacing: '2px', textTransform: 'uppercase' }}>
                  AWAY
                </p>
              </div>
              {winProbability && (
                <div className="rounded-xl px-4 py-2 text-center"
                     style={{ background: 'rgba(30,60,120,0.2)', border: '1px solid rgba(60,100,200,0.3)' }}>
                  <p style={{ fontFamily: 'Orbitron, sans-serif', fontSize: '24px', fontWeight: 900, color: '#fff', textShadow: '0 0 20px rgba(255,255,255,0.3)' }}>
                    {Math.round(winProbability.team_b_win_probability * 100)}%
                  </p>
                  <p style={{ fontFamily: 'Rajdhani, sans-serif', fontSize: '10px', fontWeight: 700, color: 'rgba(255,255,255,0.4)', letterSpacing: '1px', textTransform: 'uppercase' }}>
                    Win Prob.
                  </p>
                </div>
              )}
            </div>
          </div>
        </div>

        {/* ── Tabs ─────────────────────────────────────────────────────────────── */}
        <Tabs defaultValue="stats">
          {/* Custom game-style tab list */}
          <TabsList className="w-full gap-2 p-1 h-auto rounded-xl"
                    style={{ background: 'rgba(11,18,32,0.8)', border: '1px solid rgba(255,255,255,0.07)' }}>
            <TabsTrigger value="stats"
                         className="flex-1 flex items-center justify-center gap-2 rounded-lg py-2.5 text-xs font-bold uppercase tracking-widest transition-all data-[state=active]:shadow-none"
                         style={{ fontFamily: 'Rajdhani, sans-serif', letterSpacing: '1.5px' }}>
              <Swords size={13} />
              Team Stats
            </TabsTrigger>
            <TabsTrigger value="matchup"
                         className="flex-1 flex items-center justify-center gap-2 rounded-lg py-2.5 text-xs font-bold uppercase tracking-widest transition-all data-[state=active]:shadow-none"
                         style={{ fontFamily: 'Rajdhani, sans-serif', letterSpacing: '1.5px' }}>
              <BarChart2 size={13} />
              Player Matchup
            </TabsTrigger>
          </TabsList>

          {/* ── Tab 1: Team Stats ── */}
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
                className="flex items-center gap-2.5 rounded-xl px-5 py-2.5 text-sm font-bold uppercase tracking-widest transition-all hover:-translate-y-0.5"
                style={{
                  fontFamily: 'Rajdhani, sans-serif',
                  background: 'linear-gradient(135deg, #F9A01B, #d4860f)',
                  border: '1px solid rgba(249,160,27,0.3)',
                  color: '#080C18',
                  boxShadow: '0 0 24px rgba(249,160,27,0.3)',
                  letterSpacing: '1.5px',
                }}
              >
                <Users size={14} />
                View Recommended Lineup
              </button>
            </div>
          </TabsContent>

          {/* ── Tab 2: Player Matchup ── */}
          <TabsContent value="matchup" className="space-y-4 pt-4">
            {/* Player selectors */}
            <div className="grid grid-cols-2 gap-3">
              <PlayerSelectField
                label={teamA.name}
                value={localPlayerA}
                onChange={handlePlayerAChange}
                players={playersA}
              />
              <PlayerSelectField
                label={teamB.name}
                value={localPlayerB}
                onChange={handlePlayerBChange}
                players={playersB}
              />
            </div>

            {selectedPlayerA && selectedPlayerB ? (
              <PlayerMatchupTable
                playerA={selectedPlayerA}
                playerB={selectedPlayerB}
                matchup={matchup}
                isPending={isPendingMatchup}
              />
            ) : (
              <div className="flex items-center justify-center rounded-2xl py-14 text-center"
                   style={{ background: 'rgba(11,18,32,0.6)', border: '1px dashed rgba(255,255,255,0.08)' }}>
                <p className="text-sm" style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.3)', fontWeight: 600 }}>
                  Select one player from each team to see the matchup breakdown.
                </p>
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

// ── Player select field ───────────────────────────────────────────────────────

function PlayerSelectField({
  label, value, onChange, players,
}: {
  label: string;
  value: string;
  onChange: (v: string) => void;
  players: PlayerWithStats[];
}) {
  return (
    <div className="space-y-2">
      <label className="block text-[11px] font-bold uppercase tracking-widest"
             style={{ fontFamily: 'Rajdhani, sans-serif', color: 'rgba(255,255,255,0.4)' }}>
        {label}
      </label>
      <select
        value={value}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-xl px-4 py-3 text-sm transition-all focus:outline-none"
        style={{
          fontFamily: 'Rajdhani, sans-serif',
          fontWeight: 600,
          background: 'rgba(255,255,255,0.04)',
          border: '1px solid rgba(255,255,255,0.1)',
          color: 'rgba(255,255,255,0.8)',
          appearance: 'none',
        }}
      >
        <option value="" style={{ background: '#080C18' }}>Select player…</option>
        {players.map((p) => (
          <option key={p.id} value={String(p.id)} style={{ background: '#080C18' }}>
            #{p.jersey_number} {p.first_name} {p.last_name}
          </option>
        ))}
      </select>
    </div>
  );
}
