/**
 * Global TypeScript interfaces — mirror the DB schema exactly.
 *
 * Naming notes:
 *   offensive_rebounds  — DB column name (CSV/display label: OR)
 *   to_per_game         — DB column name (CSV/display label: TO)
 *   plus_minus          — nullable until ComputePlayerPlusMinus Job completes
 */

export interface Team {
  id: number;
  code: string;
  name: string;
  logo_path: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface Player {
  id: number;
  team_id: number;
  first_name: string;
  last_name: string;
  jersey_number: number;
  role: string | null;
  height_feet: number | null;
  weight_kg: number | null;
  profile_picture_path: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  /** Eager-loaded relationship */
  team?: Team;
  /** Eager-loaded relationship */
  stats?: PlayerStat[];
}

export interface PlayerStat {
  id: number;
  player_id: number;

  // Position / spatial
  pc: string | null;
  sd: string | null;

  // Shooting
  three_p_pct: number | null;
  three_pt: string | null;
  fg_pct: number | null;
  fg: string | null;
  ft_pct: number | null;
  ft: string | null;
  sc_eff: number | null;
  sh_eff: number | null;

  // Counting stats
  pts: number | null;
  reb: number | null;
  ast: number | null;
  ast_to: number | null;
  blk: number | null;
  stl: number | null;
  stl_to: number | null;
  dr: number | null;
  /** Maps to "OR" in CSV/display. Stored as offensive_rebounds — "or" is a MySQL reserved word. */
  offensive_rebounds: number | null;
  min: number | null;
  pf: number | null;
  /** Maps to "TO" in CSV/display. Stored as to_per_game — "to" is a MySQL reserved word. */
  to_per_game: number | null;

  // Game counts
  gp: number | null;
  gs: number | null;
  dd2: number | null;
  td3: number | null;

  // Disciplinary
  dq: number | null;
  eject: number | null;
  flag: number | null;
  tech: number | null;

  /**
   * Computed by Python analytics engine after CSV import.
   * Null until ComputePlayerPlusMinus Job completes.
   * UI must render "—" instead of null/0.
   */
  plus_minus: number | null;

  /**
   * Advanced stats — computed by PHP service layer from aggregated history.
   * All nullable until RebuildPlayerStats Job completes.
   * UI must render "—" instead of null/0.
   */
  eff:     number | null;
  efg_pct: number | null;
  ts_pct:  number | null;

  created_at: string;
  updated_at: string;
}

export type CsvImportStatus = 'pending' | 'processing' | 'completed' | 'failed';

export interface CsvImport {
  id: number;
  team_id: number;
  filename: string;
  status: CsvImportStatus;
  rows_imported: number;
  error_log: string | null;
  created_at: string;
  updated_at: string;
  /** Eager-loaded relationship */
  team?: Team;
}

// ─── Composite Types ─────────────────────────────────────────────────────────

/**
 * Player with their most recent PlayerStat row eager-loaded.
 * `stats` is an array containing 0 or 1 entry from the server.
 * Use `player.stats[0] ?? null` to access the stat row.
 */
export interface PlayerWithStats extends Player {
  stats: PlayerStat[];
}

// ─── Comparison / Aggregate ──────────────────────────────────────────────────

export interface TeamAggregateStats {
  avg_pts: number;
  avg_reb: number;
  avg_ast: number;
  avg_fg_pct: number;
  avg_blk: number;
  avg_stl: number;
  avg_to_per_game: number;
}

// ─── Analytics Engine Contracts ──────────────────────────────────────────────

export interface LineupPlayer {
  player_id: number;
  name: string;
  /** Null for a player drafted in to fill a short lineup — they were never ranked. */
  plus_minus_score: number | null;
}

export interface LineupRecommendation {
  recommended_lineup: LineupPlayer[];
  confidence: number;
}

/** Why the live feed held a player back. Mirrors LiveLineupEligibilityFilter's constants. */
export type LiveLineupReason =
  | 'disqualified'
  | 'foul_trouble'
  | 'inactive'
  | 'assigned_to_assistant';

/**
 * The two rankings are deliberately NOT merged: one is a 20-game rating, the other an
 * 8-minute sample, and adding them would need an exchange rate the data can't justify.
 * The coach is the merge function.
 */
export interface LiveLineupSuggestion {
  season: LineupRecommendation;
  tonight: LineupRecommendation;
}

export interface LiveLineupSuggestionResponse {
  pending: boolean;
  team_id: number;
  suggestion: LiveLineupSuggestion | null;
  reasons: Record<string, LiveLineupReason>;
  /** On court but assigned to another coach: they hold a place in the five and cannot be moved. */
  fixed_player_ids: number[];
  /** How many of the five this coach may fill. 5 when no assistant is assigned. */
  slot_count: number;
  controlled_player_ids: number[];
}

export interface WinProbabilityResult {
  team_a_win_probability: number;
  team_b_win_probability: number;
  team_a_win_rate: number;
  team_b_win_rate: number;
}

export interface PlayerMatchupResult {
  player_a_edge_score: number;
  player_b_edge_score: number;
  stronger_stats_a: string[];
  stronger_stats_b: string[];
}

// ─── Inertia Page Props ───────────────────────────────────────────────────────

export interface PageProps {
  auth: {
    user: {
      id: number;
      name: string;
      email: string;
      team_id: number | null;
      team: { id: number; name: string } | null;
    } | null;
  };
  flash?: {
    success?: string;
    error?: string;
  };
  liveGameInvite?: LiveGameInviteBanner | null;
  /** Required by Inertia's PageProps constraint. */
  [key: string]: unknown;
}

export type LiveGameInviteKind = 'opponent_setup' | 'home_assigned' | 'home_team';

export interface LiveGameInviteBanner {
  id: string;
  live_game_id: number;
  kind: LiveGameInviteKind;
  title: string;
  body: string;
  home_team_name: string;
  opponent_team_name: string;
}

export type LiveGameStatus = 'setup' | 'live' | 'finished';

export interface LiveGame {
  id: number;
  home_team_id: number;
  opponent_team_id: number;
  created_by_user_id: number | null;
  status: LiveGameStatus;
  game_date: string | null;
  period_length_seconds: number;
  current_period: number;
  clock_seconds_remaining: number;
  clock_running: boolean;
  home_score: number;
  opponent_score: number;
  starting_player_ids: number[] | null;
  active_player_ids: number[] | null;
  opponent_starting_player_ids: number[] | null;
  opponent_active_player_ids: number[] | null;
  started_at: string | null;
  finished_at: string | null;
  home_team?: Team;
  opponent_team?: Team;
}

export interface LiveGameClock {
  period: number;
  period_length_seconds: number;
  seconds_remaining: number;
  running: boolean;
  server_now: string;
}

export interface LiveGamePlayerStat {
  player_id: number;
  is_starter: boolean;
  is_active: boolean;
  minutes_seconds: number;
  plus_minus: number;
  points: number;
  field_goals_made: number;
  field_goals_attempted: number;
  three_pointers_made: number;
  three_pointers_attempted: number;
  free_throws_made: number;
  free_throws_attempted: number;
  offensive_rebounds: number;
  defensive_rebounds: number;
  rebounds: number;
  assists: number;
  steals: number;
  blocks: number;
  turnovers: number;
  personal_fouls: number;
  flagrant_fouls: number;
  technical_fouls: number;
}

export interface LiveGameEvent {
  id: number;
  sequence: number;
  type: string;
  team_scope: 'own' | 'opponent' | 'game';
  player_id: number | null;
  period: number;
  clock_seconds_remaining: number;
  occurred_at: string;
  payload: Record<string, unknown>;
  voids_event_id: number | null;
  recorded_by_user_id: number | null;
}

export interface LiveGameAlert {
  id: number;
  player_id: number | null;
  team_id: number | null;
  type: string;
  severity: string;
  period: number;
  clock_seconds_remaining: number;
  message: string;
  context: Record<string, unknown>;
  triggered_at: string | null;
  resolved_at: string | null;
}

export interface LiveGameSnapshot {
  liveGame: Pick<LiveGame, 'id' | 'home_team_id' | 'opponent_team_id' | 'status' | 'game_date' | 'period_length_seconds' | 'current_period'>;
  score: { home: number; opponent: number };
  clock: LiveGameClock;
  active_player_ids: number[];
  opponent_active_player_ids: number[];
  home_lineup_ready: boolean;
  opponent_lineup_ready: boolean;
  both_lineups_ready: boolean;
  stats: LiveGamePlayerStat[];
  events: LiveGameEvent[];
  alerts: LiveGameAlert[];
}

declare global {
  interface Window {
    Echo?: {
      private(channel: string): {
        // Echo payloads vary by event; callers narrow.
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        listen(event: string, callback: (payload: any) => void): unknown;
        notification(callback: (notification: Record<string, unknown>) => void): unknown;
      };
      leave(channel: string): void;
    };
  }
}
