import { Badge } from '@/Components/ui/badge';
import { type CsvImport, type CsvImportStatus } from '@/types';
import { router } from '@inertiajs/react';
import { AlertCircle, CheckCircle2, Clock, Loader2 } from 'lucide-react';
import { useEffect } from 'react';

interface ImportStatusProps {
  latestImport: CsvImport | null;
}

const STATUS_CONFIG: Record<
  CsvImportStatus,
  { label: string; variant: 'default' | 'secondary' | 'destructive' | 'outline'; icon: React.ReactNode }
> = {
  pending: {
    label: 'Queued',
    variant: 'secondary',
    icon: <Clock size={13} />,
  },
  processing: {
    label: 'Processing…',
    variant: 'default',
    icon: <Loader2 size={13} className="animate-spin" />,
  },
  completed: {
    label: 'Imported',
    variant: 'outline',
    icon: <CheckCircle2 size={13} className="text-green-500" />,
  },
  failed: {
    label: 'Failed',
    variant: 'destructive',
    icon: <AlertCircle size={13} />,
  },
};

/**
 * Displays the latest CSV import status for a team.
 * Polls via Inertia partial reload while the job is pending or processing.
 *
 * Spec rule: plus_minus is null until ComputePlayerPlusMinus completes.
 * This component communicates that state to the user.
 */
export function ImportStatus({ latestImport }: ImportStatusProps) {
  const isActive = latestImport?.status === 'pending' || latestImport?.status === 'processing';

  // Poll every 2 seconds while the job is in-flight
  useEffect(() => {
    if (!isActive) return;

    const timer = setInterval(() => {
      router.reload({ only: ['latestImport', 'players'] });
    }, 2000);

    return () => clearInterval(timer);
  }, [isActive]);

  if (!latestImport) return null;

  const config = STATUS_CONFIG[latestImport.status];

  return (
    <div className="rounded-xl border border-border bg-card p-4">
      <div className="flex items-center justify-between gap-4">
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium text-foreground">Last import:</span>
          <span className="text-sm text-muted-foreground truncate max-w-[200px]">
            {latestImport.filename.split('/').pop()}
          </span>
        </div>

        <div className="flex items-center gap-2 shrink-0">
          <Badge variant={config.variant} className="gap-1.5">
            {config.icon}
            {config.label}
          </Badge>
          {latestImport.status === 'completed' && (
            <span className="text-xs text-muted-foreground">
              {latestImport.rows_imported} rows
            </span>
          )}
        </div>
      </div>

      {/* Error log */}
      {latestImport.status === 'failed' && latestImport.error_log && (
        <details className="mt-3">
          <summary className="cursor-pointer text-xs font-medium text-destructive">
            View error details
          </summary>
          <pre className="mt-2 overflow-x-auto rounded-md bg-destructive/10 p-3 text-xs text-destructive whitespace-pre-wrap">
            {latestImport.error_log}
          </pre>
        </details>
      )}

      {/* Partial-import warnings */}
      {latestImport.status === 'completed' && latestImport.error_log && (
        <details className="mt-3">
          <summary className="cursor-pointer text-xs font-medium text-accent-foreground">
            Some rows had warnings
          </summary>
          <pre className="mt-2 overflow-x-auto rounded-md bg-accent/10 p-3 text-xs whitespace-pre-wrap">
            {latestImport.error_log}
          </pre>
        </details>
      )}
    </div>
  );
}
