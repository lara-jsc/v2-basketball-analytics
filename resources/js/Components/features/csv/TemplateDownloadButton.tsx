import { Button } from '@/Components/ui/button';
import { Download } from 'lucide-react';

/**
 * Simple anchor-based download button for the CSV roster template.
 * Uses a standard link so the browser handles the file download directly.
 */
export function TemplateDownloadButton() {
  return (
    <a href={route('csv.template')} download>
      <Button variant="outline" size="sm" className="gap-2">
        <Download size={14} />
        Download Template
      </Button>
    </a>
  );
}
