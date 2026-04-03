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

// ─── Analytics Engine Contracts ──────────────────────────────────────────────

export interface LineupPlayer {
  player_id: number;
  name: string;
  plus_minus_score: number;
}

export interface LineupRecommendation {
  recommended_lineup: LineupPlayer[];
  confidence: number;
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
    };
  };
  flash?: {
    success?: string;
    error?: string;
  };
}
