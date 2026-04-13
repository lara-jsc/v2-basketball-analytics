import { TemplateDownloadButton } from './TemplateDownloadButton';
import { useForm } from '@inertiajs/react';
import { FileUp, Loader2, Paperclip } from 'lucide-react';
import { type DragEvent, type FormEvent, useRef, useState } from 'react';

interface CsvUploadFormProps {
    teamId: number;
}

interface UploadFormData {
    team_id: number;
    file: File | null;
}

/**
 * CSV roster upload form — arena-themed with drag-and-drop support.
 */
export function CsvUploadForm({ teamId }: CsvUploadFormProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [isDragOver, setIsDragOver] = useState(false);
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

    function handleDrop(e: DragEvent<HTMLDivElement>) {
        e.preventDefault();
        setIsDragOver(false);
        const file = e.dataTransfer.files?.[0];
        if (file) setData('file', file);
    }

    function handleDragOver(e: DragEvent<HTMLDivElement>) {
        e.preventDefault();
        setIsDragOver(true);
    }

    return (
        <div className="rounded-xl border border-border bg-card overflow-hidden">
            {/* Header band — arena floor texture */}
            <div className="flex items-center justify-between gap-4 border-b border-border px-5 py-3">
                <div>
                    <h3 className="font-display text-sm font-bold tracking-widest text-foreground uppercase">
                        CSV Import
                    </h3>
                    <p className="mt-0.5 text-xs text-muted-foreground font-ui">
                        Upload a roster file to create your player list. Add game history per player to populate stats.
                    </p>
                </div>
                <div className="relative">
                    <TemplateDownloadButton />
                </div>
            </div>

            {/* Upload area */}
            <form onSubmit={handleSubmit} className="px-5 py-4 space-y-3">
                {/* Drop zone */}
                <div
                    onDrop={handleDrop}
                    onDragOver={handleDragOver}
                    onDragLeave={() => setIsDragOver(false)}
                    className={[
                        'flex items-center gap-3 rounded-lg border-2 border-dashed px-3 py-2.5 transition-all',
                        isDragOver
                            ? 'border-accent/60 bg-accent/8'
                            : 'border-border/60 bg-muted/20 hover:border-border hover:bg-muted/30',
                    ].join(' ')}
                >
                    <input
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

                    <Paperclip size={13} className={`shrink-0 ${isDragOver ? 'text-accent' : 'text-muted-foreground'}`} />
                    <span className={`flex-1 text-sm truncate font-ui ${data.file ? 'text-foreground' : 'text-muted-foreground'}`}>
                        {isDragOver
                            ? 'Drop your CSV here…'
                            : data.file
                            ? data.file.name
                            : 'No file selected — drag & drop or browse'}
                    </span>

                    {/* Browse button */}
                    <button
                        type="button"
                        disabled={processing}
                        onClick={() => fileInputRef.current?.click()}
                        className="shrink-0 rounded-md border border-border bg-card px-3 py-1.5 text-xs font-ui font-semibold tracking-wide text-foreground transition-colors hover:bg-muted disabled:opacity-50"
                    >
                        Browse
                    </button>

                    {/* Import CTA — amber */}
                    <button
                        type="submit"
                        disabled={processing || data.file === null}
                        className="flex shrink-0 items-center gap-2 rounded-md bg-accent px-4 py-1.5 text-xs font-ui font-semibold tracking-wide text-accent-foreground transition-all hover:opacity-90 hover:shadow-[0_0_12px_rgba(249,160,27,0.4)] disabled:opacity-40"
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
                    <p className="text-xs text-destructive font-ui">{errors.file}</p>
                )}

                <p className="text-[11px] text-muted-foreground font-ui">
                    CSV headers must match the template exactly. Download the template above to get started.
                </p>
            </form>
        </div>
    );
}
