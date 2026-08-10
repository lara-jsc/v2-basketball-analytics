/**
 * PlayerHistory feature types.
 *
 * Reserved word reminders:
 *   turnovers          — DB column name (CSV/display label: TO)
 *   offensive_rebounds — DB column name (CSV/display label: OR)
 */

import type { PageProps, Player, Team } from '@/types';

// ─── Core Model ───────────────────────────────────────────────────────────────

export interface PlayerHistory {
  id: number;
  player_id: number;
  playing_team_id: number;
  opponent_team_id: number;

  game_date: string; // YYYY-MM-DD

  position_played: string | null;
  minutes_played: number | null;

  // Shooting
  points: number | null;
  field_goals_made: number | null;
  field_goals_attempted: number | null;
  three_pointers_made: number | null;
  three_pointers_attempted: number | null;
  free_throws_made: number | null;
  free_throws_attempted: number | null;

  // Rebounds
  /** Maps to "OR" in CSV/display. Stored as offensive_rebounds — "or" is a MySQL reserved word. */
  offensive_rebounds: number | null;
  defensive_rebounds: number | null;
  rebounds: number | null;

  // Per-game counting
  assists: number | null;
  steals: number | null;
  blocks: number | null;
  /** Maps to "TO" in CSV/display. Stored as turnovers — "to" is a MySQL reserved word. */
  turnovers: number | null;
  personal_fouls: number | null;

  // Disciplinary
  flagrant_fouls: number | null;
  technical_fouls: number | null;
  ejections: number | null;
  disqualifications: number | null;

  is_started: boolean;
  notes: string | null;

  created_at: string;
  updated_at: string;

  // Eager-loaded relationships
  player?: Player;
  playing_team?: Team;
  opponent_team?: Team;
}

// ─── Form Data ────────────────────────────────────────────────────────────────

/**
 * Shape of data submitted from PlayerHistoryForm for both store and update.
 * All stat fields are optional (nullable) — only game_date and opponent_team_id are required on create.
 */
export interface PlayerHistoryFormData {
  opponent_team_id: number | '';
  game_date: string;
  position_played: string;
  minutes_played: string;
  points: string;
  field_goals_made: string;
  field_goals_attempted: string;
  three_pointers_made: string;
  three_pointers_attempted: string;
  free_throws_made: string;
  free_throws_attempted: string;
  offensive_rebounds: string;
  defensive_rebounds: string;
  rebounds: string;
  assists: string;
  steals: string;
  blocks: string;
  turnovers: string;
  personal_fouls: string;
  flagrant_fouls: string;
  technical_fouls: string;
  ejections: string;
  disqualifications: string;
  is_started: boolean;
  notes: string;
}

// ─── Filters ──────────────────────────────────────────────────────────────────

export interface PlayerHistoryFilters {
  from?: string;
  to?: string;
  playing_team_id?: number;
  opponent_team_id?: number;
}

// ─── Inertia Page Props ───────────────────────────────────────────────────────

export interface PlayerHistoryIndexProps extends PageProps {
  player: Player & { team: Team };
  histories: PlayerHistory[];
  teams: Pick<Team, 'id' | 'code' | 'name'>[];
  filters: PlayerHistoryFilters;
  exportUrl: string;
}

export interface PlayerHistoryCreateProps extends PageProps {
  player: Player & { team: Team };
  playingTeam: Pick<Team, 'id' | 'code' | 'name'>;
  opponentTeams: Pick<Team, 'id' | 'code' | 'name'>[];
}

export interface PlayerHistoryEditProps extends PageProps {
  history: PlayerHistory & {
    player: Player & { team: Team };
    playing_team: Team;
    opponent_team: Team;
  };
  playingTeam: Pick<Team, 'id' | 'code' | 'name'>;
  opponentTeams: Pick<Team, 'id' | 'code' | 'name'>[];
}
