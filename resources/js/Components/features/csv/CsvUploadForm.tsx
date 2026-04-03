import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { TemplateDownloadButton } from './TemplateDownloadButton';
import { useForm } from '@inertiajs/react';
import { FileUp, Loader2 } from 'lucide-react';
import { type FormEvent } from 'react';

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
 * Validates file selection client-side before submitting.
 */
export function CsvUploadForm({ teamId }: CsvUploadFormProps) {
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
    <div className="space-y-4 rounded-xl border border-border bg-card p-5">
      <div className="flex items-start justify-between gap-4">
        <div>
          <h3 className="font-semibold text-foreground">Upload Roster CSV</h3>
          <p className="mt-0.5 text-sm text-muted-foreground">
            Upload a CSV file matching the template to import player stats.
          </p>
        </div>
        <TemplateDownloadButton />
      </div>

      <form onSubmit={handleSubmit} className="space-y-3">
        <div className="space-y-1.5">
          <Label htmlFor="csv-file">CSV File</Label>
          <Input
            id="csv-file"
            type="file"
            accept=".csv,text/csv"
            disabled={processing}
            onChange={(e) => {
              const file = e.target.files?.[0] ?? null;
              setData('file', file);
            }}
            className="cursor-pointer file:cursor-pointer file:border-0 file:bg-transparent file:text-sm file:font-medium"
          />
          {errors.file && (
            <p className="text-xs text-destructive">{errors.file}</p>
          )}
        </div>

        <Button
          type="submit"
          disabled={processing || data.file === null}
          className="gap-2"
        >
          {processing ? (
            <>
              <Loader2 size={14} className="animate-spin" />
              Uploading…
            </>
          ) : (
            <>
              <FileUp size={14} />
              Upload & Import
            </>
          )}
        </Button>
      </form>
    </div>
  );
}
