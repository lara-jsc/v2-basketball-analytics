import { type LineupRecommendation } from '@/types';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Badge } from '@/Components/ui/badge';
import { Loader2 } from 'lucide-react';

interface LineupModalProps {
  open: boolean;
  onClose: () => void;
  lineup: LineupRecommendation | null;
  teamName: string;
}

/**
 * Modal displaying the recommended starting lineup for the home team.
 * Shows a spinner while the lineup job is pending (lineup === null).
 * Spec: recommended_lineup is sorted by plus_minus_score descending (engine handles sort).
 */
export function LineupModal({ open, onClose, lineup, teamName }: LineupModalProps) {
  return (
    <Dialog open={open} onOpenChange={(v) => !v && onClose()}>
      <DialogContent className="max-w-sm">
        <DialogHeader>
          <DialogTitle className="text-base">
            Recommended Starting 5
          </DialogTitle>
          <p className="text-xs text-muted-foreground mt-0.5">{teamName}</p>
        </DialogHeader>

        {lineup === null ? (
          <div className="flex items-center gap-2 py-6 text-sm text-muted-foreground">
            <Loader2 size={14} className="animate-spin" />
            Computing lineup recommendation…
          </div>
        ) : (
          <div className="space-y-3 pt-1">
            <ol className="space-y-2">
              {lineup.recommended_lineup.map((player, idx) => (
                <li
                  key={player.player_id}
                  className="flex items-center gap-3 rounded-lg border border-border bg-muted/30 px-3 py-2.5"
                >
                  <span className="w-5 text-center text-xs font-semibold text-muted-foreground">
                    {idx + 1}
                  </span>
                  <span className="flex-1 text-sm font-medium text-foreground truncate">
                    {player.name}
                  </span>
                  <Badge
                    variant="outline"
                    className={`text-xs tabular-nums shrink-0 ${
                      player.plus_minus_score >= 0
                        ? 'border-accent/40 text-accent'
                        : 'text-muted-foreground'
                    }`}
                  >
                    {player.plus_minus_score >= 0 ? '+' : ''}
                    {player.plus_minus_score.toFixed(1)}
                  </Badge>
                </li>
              ))}
            </ol>

            <div className="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2 text-xs">
              <span className="text-muted-foreground">Confidence</span>
              <span className="font-semibold text-foreground">
                {Math.round(lineup.confidence * 100)}%
              </span>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
