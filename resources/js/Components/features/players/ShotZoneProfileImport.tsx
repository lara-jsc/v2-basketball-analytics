import { useForm } from '@inertiajs/react';
import { Download, FileUp, Loader2, Paperclip } from 'lucide-react';
import { type FormEvent, useRef } from 'react';

interface ShotZoneProfileImportProps {
    /** The team whose players' profiles this import targets. */
    teamId: number;
}

interface ImportFormData {
    file: File | null;
}

/**
 * Shot zone profile CSV import panel.
 *
 * CSV columns (order-sensitive):
 *   player_id, paint_made, paint_attempted, mid_range_made, mid_range_attempted,
 *   corner_3_left_made, corner_3_left_attempted, corner_3_right_made, corner_3_right_attempted,
 *   above_break_3_made, above_break_3_attempted
 *
 * One row per player. Re-import replaces the player's existing profile.
 */
export function ShotZoneProfileImport({ teamId }: ShotZoneProfileImportProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm<ImportFormData>({
        file: null,
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>): void => {
        e.preventDefault();
        post(route('shot-zone-profiles.import', { team: teamId }), {
            forceFormData: true,
            onSuccess: () => reset('file'),
        });
    };

    return (
        <div className="rounded-xl border border-border bg-card overflow-hidden">
            {/* Header band */}
            <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-3">
                <div>
                    <h3 className="font-display text-sm font-bold tracking-wide text-foreground uppercase">
                        Import Shot Zone Profile
                    </h3>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Upload a CSV with one row per player to seed shot zone heat maps.
                    </p>
                </div>

                {/* Template Download */}
                <a
                    href={route('shot-zone-profiles.template')}
                    download
                    className="flex shrink-0 items-center gap-1.5 rounded-lg border border-accent px-3 py-1.5 text-xs font-ui font-semibold tracking-wide text-accent transition-colors hover:bg-accent/10"
                >
                    <Download size={13} />
                    Download Template (.csv)
                </a>
            </div>

            {/* Upload row */}
            <form onSubmit={handleSubmit} className="px-5 py-4">
                <div className="flex items-center gap-3">
                    {/* Hidden native file input */}
                    <input
                        ref={fileInputRef}
                        id="shot-zone-csv-file"
                        type="file"
                        accept=".csv,text/csv"
                        disabled={processing}
                        className="sr-only"
                        onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                    />

                    {/* Filename display */}
                    <div className="flex flex-1 items-center gap-2 rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm">
                        <Paperclip size={13} className="shrink-0 text-muted-foreground" />
                        <span className={data.file ? 'text-foreground truncate' : 'text-muted-foreground'}>
                            {data.file ? data.file.name : 'No file selected'}
                        </span>
                    </div>

                    {/* Browse */}
                    <button
                        type="button"
                        disabled={processing}
                        onClick={() => fileInputRef.current?.click()}
                        className="shrink-0 rounded-lg border border-border bg-card px-3 py-2 text-xs font-ui font-semibold tracking-wide text-foreground transition-colors hover:bg-muted disabled:opacity-50"
                    >
                        Browse
                    </button>

                    {/* Import CTA */}
                    <button
                        type="submit"
                        disabled={processing || data.file === null}
                        className="flex shrink-0 items-center gap-2 rounded-lg bg-accent px-4 py-2 text-xs font-ui font-semibold tracking-wide text-accent-foreground transition-opacity hover:opacity-90 disabled:opacity-40"
                    >
                        {processing ? (
                            <>
                                <Loader2 size={13} className="animate-spin" />
                                Importing…
                            </>
                        ) : (
                            <>
                                <FileUp size={13} />
                                Import Profile
                            </>
                        )}
                    </button>
                </div>

                {errors.file && (
                    <p className="mt-2 text-xs text-destructive">{errors.file}</p>
                )}

                <p className="mt-2 text-[11px] text-muted-foreground">
                    One row per player. Re-import replaces the existing profile. Zone totals must match
                    the player's box-score history (FGA / 3PA splits) when history exists.
                </p>
            </form>
        </div>
    );
}
