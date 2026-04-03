import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { type PlayerStat, type PlayerWithStats } from '@/types';

interface PlayersTableProps {
  players: PlayerWithStats[];
}

/**
 * Horizontally-scrollable stats table for all players on a team.
 *
 * Column notes:
 *   OR  → offensive_rebounds in DB (MySQL reserved word workaround)
 *   TO  → to_per_game in DB         (MySQL reserved word workaround)
 *   +/- → plus_minus; null until Python BPM Job completes — renders "—"
 */
export function PlayersTable({ players }: PlayersTableProps) {
  if (players.length === 0) {
    return (
      <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card py-12 text-center">
        <p className="text-sm font-medium text-muted-foreground">No players yet</p>
        <p className="mt-1 text-xs text-muted-foreground">
          Upload a CSV file to import your roster.
        </p>
      </div>
    );
  }

  return (
    <div className="overflow-x-auto rounded-xl border border-border bg-card">
      <Table>
        <TableHeader>
          <TableRow className="bg-muted/50">
            {/* Player identity */}
            <TableHead className="sticky left-0 z-10 bg-muted/50 whitespace-nowrap">#</TableHead>
            <TableHead className="sticky left-8 z-10 bg-muted/50 whitespace-nowrap min-w-[140px]">Name</TableHead>
            <TableHead className="whitespace-nowrap">Role</TableHead>
            <TableHead className="whitespace-nowrap">Status</TableHead>
            {/* Key computed stat first */}
            <TableHead className="whitespace-nowrap text-accent font-semibold">+/-</TableHead>
            {/* Core per-game stats */}
            <TableHead className="whitespace-nowrap">PTS</TableHead>
            <TableHead className="whitespace-nowrap">REB</TableHead>
            <TableHead className="whitespace-nowrap">AST</TableHead>
            <TableHead className="whitespace-nowrap">BLK</TableHead>
            <TableHead className="whitespace-nowrap">STL</TableHead>
            <TableHead className="whitespace-nowrap">TO</TableHead>
            <TableHead className="whitespace-nowrap">MIN</TableHead>
            {/* Shooting */}
            <TableHead className="whitespace-nowrap">FG%</TableHead>
            <TableHead className="whitespace-nowrap">FG</TableHead>
            <TableHead className="whitespace-nowrap">3P%</TableHead>
            <TableHead className="whitespace-nowrap">3PT</TableHead>
            <TableHead className="whitespace-nowrap">FT%</TableHead>
            <TableHead className="whitespace-nowrap">FT</TableHead>
            <TableHead className="whitespace-nowrap">SC-EFF</TableHead>
            <TableHead className="whitespace-nowrap">SH-EFF</TableHead>
            {/* Rebounding */}
            <TableHead className="whitespace-nowrap">DR</TableHead>
            <TableHead className="whitespace-nowrap">OR</TableHead>
            {/* Ratios */}
            <TableHead className="whitespace-nowrap">AST/TO</TableHead>
            <TableHead className="whitespace-nowrap">STL/TO</TableHead>
            {/* Fouls / discipline */}
            <TableHead className="whitespace-nowrap">PF</TableHead>
            <TableHead className="whitespace-nowrap">FLAG</TableHead>
            <TableHead className="whitespace-nowrap">TECH</TableHead>
            <TableHead className="whitespace-nowrap">EJECT</TableHead>
            <TableHead className="whitespace-nowrap">DQ</TableHead>
            {/* Games */}
            <TableHead className="whitespace-nowrap">GP</TableHead>
            <TableHead className="whitespace-nowrap">GS</TableHead>
            <TableHead className="whitespace-nowrap">DD2</TableHead>
            <TableHead className="whitespace-nowrap">TD3</TableHead>
            {/* Position */}
            <TableHead className="whitespace-nowrap">PC</TableHead>
            <TableHead className="whitespace-nowrap">SD</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {players.map((player) => {
            const stat: PlayerStat | null = player.stats[0] ?? null;
            return (
              <TableRow key={player.id} className="hover:bg-muted/30">
                <TableCell className="sticky left-0 z-10 bg-card font-medium">
                  {player.jersey_number}
                </TableCell>
                <TableCell className="sticky left-8 z-10 bg-card whitespace-nowrap font-medium">
                  {player.first_name} {player.last_name}
                </TableCell>
                <TableCell className="whitespace-nowrap text-muted-foreground text-xs">
                  {player.role ?? '—'}
                </TableCell>
                <TableCell>
                  <Badge variant={player.is_active ? 'outline' : 'secondary'} className="text-xs">
                    {player.is_active ? 'Active' : 'Inactive'}
                  </Badge>
                </TableCell>
                {/* +/- — null until Python job completes */}
                <TableCell className="font-semibold text-accent whitespace-nowrap">
                  {formatPlusMinus(stat?.plus_minus ?? null)}
                </TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.pts)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.reb)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.ast)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.blk)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.stl)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.to_per_game)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.min)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.fg_pct)}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.fg ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.three_p_pct)}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.three_pt ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.ft_pct)}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.ft ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.sc_eff)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.sh_eff)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.dr)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.offensive_rebounds)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.ast_to)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.stl_to)}</TableCell>
                <TableCell className="whitespace-nowrap">{fmt(stat?.pf)}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.flag ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.tech ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.eject ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.dq ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.gp ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.gs ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.dd2 ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap">{stat?.td3 ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap text-muted-foreground text-xs">{stat?.pc ?? '—'}</TableCell>
                <TableCell className="whitespace-nowrap text-muted-foreground text-xs">{stat?.sd ?? '—'}</TableCell>
              </TableRow>
            );
          })}
        </TableBody>
      </Table>
    </div>
  );
}

// ── Formatting helpers ──────────────────────────────────────────────────────

function fmt(value: number | null | undefined): string {
  if (value === null || value === undefined) return '—';
  return value.toString();
}

/**
 * Format plus_minus with a leading sign (e.g. +7.4, −1.8).
 * Returns "—" when null (job not yet complete — spec requirement).
 */
function formatPlusMinus(value: number | null): string {
  if (value === null) return '—';
  return value >= 0 ? `+${value.toFixed(1)}` : `${value.toFixed(1)}`;
}
