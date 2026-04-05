import { useForm } from '@inertiajs/react';
import { Download, FileUp, Loader2, Paperclip } from 'lucide-react';
import { type FormEvent, useRef } from 'react';

interface PlayerHistoryImportProps {
    /** The player whose history this import belongs to. */
    playerId: number;
}

interface ImportFormData {
    file: File | null;
}

/**
 * Player history xlsx import panel.
 *
 * - Template Download button: amber outlined, icon: download arrow
 * - Import History button: amber filled CTA
 * - player_id is taken from the route — not submitted in the form
 */
export function PlayerHistoryImport({ playerId }: PlayerHistoryImportProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm<ImportFormData>({
        file: null,
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>): void => {
        e.preventDefault();
        post(route('player-histories.import', { player: playerId }), {
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
                        Import Game History
                    </h3>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Upload the filled Excel template (.xlsx) to import multiple game entries at once.
                    </p>
                </div>

                {/* Template Download — amber outlined */}
                <a
                    href={route('player-histories.template')}
                    download
                    className="flex shrink-0 items-center gap-1.5 rounded-lg border border-accent px-3 py-1.5 text-xs font-ui font-semibold tracking-wide text-accent transition-colors hover:bg-accent/10"
                >
                    <Download size={13} />
                    Download Template (.xlsx)
                </a>
            </div>

            {/* Upload row */}
            <form onSubmit={handleSubmit} className="px-5 py-4">
                <div className="flex items-center gap-3">
                    {/* Hidden native file input */}
                    <input
                        ref={fileInputRef}
                        id="history-xlsx-file"
                        type="file"
                        accept=".xlsx,.xls,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel"
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

                    {/* Import History CTA — amber filled */}
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
                                Import History
                            </>
                        )}
                    </button>
                </div>

                {errors.file && (
                    <p className="mt-2 text-xs text-destructive">{errors.file}</p>
                )}

                <p className="mt-2 text-[11px] text-muted-foreground">
                    Use the downloaded template — select teams from the dropdown cells. Delete the example row before uploading.
                </p>
            </form>
        </div>
    );
}
