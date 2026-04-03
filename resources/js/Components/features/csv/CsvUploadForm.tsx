import { TemplateDownloadButton } from './TemplateDownloadButton';
import { useForm } from '@inertiajs/react';
import { FileUp, Loader2, Paperclip } from 'lucide-react';
import { type FormEvent, useRef } from 'react';

interface CsvUploadFormProps {
    teamId: number;
}

interface UploadFormData {
    team_id: number;
    file: File | null;
}

/**
 * CSV roster upload form.
 * Submits via Inertia multipart POST to csv.upload.
 *
 * UX: custom file picker row (filename display + Browse + Import Data)
 * so the native file input chrome is hidden. Template download is a
 * secondary text link — not a CTA-weight button.
 */
export function CsvUploadForm({ teamId }: CsvUploadFormProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { data, setData, post, processing, errors, reset } = useForm<UploadFormData>({
        team_id: teamId,
        file: null,
    });

    const handleSubmit = (e: FormEvent<HTMLFormElement>): void => {
        e.preventDefault();
        post(route('csv.upload'), {
            forceFormData: true,
            onSuccess: () => reset('file'),
        });
    };

    return (
        <div className="rounded-xl border border-border bg-card overflow-hidden">
            {/* Header band */}
            <div className="flex items-center justify-between gap-4 border-b border-border bg-gradient-to-r from-primary/10 to-transparent px-5 py-3">
                <div>
                    <h3 className="font-display text-sm font-bold tracking-wide text-foreground uppercase">
                        CSV Import
                    </h3>
                    <p className="mt-0.5 text-xs text-muted-foreground">
                        Upload a roster file to import player stats in bulk.
                    </p>
                </div>
                <TemplateDownloadButton />
            </div>

            {/* Upload row */}
            <form onSubmit={handleSubmit} className="px-5 py-4">
                <div className="flex items-center gap-3">
                    {/* Hidden native file input */}
                    <input
                        ref={fileInputRef}
                        type="hidden"
                        name="team_id"
                        value={teamId}
                    />
                    <input
                        ref={fileInputRef}
                        id="csv-file"
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

                    {/* Browse button */}
                    <button
                        type="button"
                        disabled={processing}
                        onClick={() => fileInputRef.current?.click()}
                        className="shrink-0 rounded-lg border border-border bg-card px-3 py-2 text-xs font-ui font-semibold tracking-wide text-foreground transition-colors hover:bg-muted disabled:opacity-50"
                    >
                        Browse
                    </button>

                    {/* Import CTA — amber */}
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
                                Import Data
                            </>
                        )}
                    </button>
                </div>

                {errors.file && (
                    <p className="mt-2 text-xs text-destructive">{errors.file}</p>
                )}

                <p className="mt-2 text-[11px] text-muted-foreground">
                    CSV headers must match the template exactly. Download the template above to get started.
                </p>
            </form>
        </div>
    );
}
