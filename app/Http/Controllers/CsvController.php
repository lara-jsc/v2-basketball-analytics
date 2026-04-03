<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCsvUploadRequest;
use App\Jobs\ProcessCsvImport;
use App\Repositories\CsvImportRepository;
use App\Services\CsvTemplateService;
use App\Services\WinProbabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvController extends Controller
{
    public function __construct(
        private readonly CsvTemplateService $templateService,
        private readonly CsvImportRepository $csvImportRepository,
        private readonly WinProbabilityService $winProbabilityService,
    ) {}

    /**
     * Stream the CSV roster template for download.
     * Headers match the spec exactly — any deviation on upload will be rejected.
     */
    public function template(): StreamedResponse
    {
        $content = $this->templateService->generateTemplateContent();

        return response()->streamDownload(
            function () use ($content) {
                echo $content;
            },
            'hoopsense-roster-template.csv',
            [
                'Content-Type'        => 'text/csv',
                'Content-Disposition' => 'attachment; filename="hoopsense-roster-template.csv"',
            ],
        );
    }

    /**
     * Accept a CSV upload for a team, store it, and dispatch the import Job.
     * Redirects to the team's show page where the user can track progress.
     */
    public function upload(StoreCsvUploadRequest $request): RedirectResponse
    {
        $teamId = (int) $request->validated()['team_id'];
        $file   = $request->file('file');

        // Store the file at imports/{team_id}/{original_name} — not publicly accessible
        $storagePath = $file->storeAs(
            "imports/{$teamId}",
            $file->getClientOriginalName(),
        );

        $import = $this->csvImportRepository->create([
            'team_id'  => $teamId,
            'filename' => $storagePath,
            'status'   => 'pending',
        ]);

        ProcessCsvImport::dispatch($import->id);

        // Invalidate all cached comparison results involving this team
        $this->winProbabilityService->invalidateForTeam($teamId);

        return redirect()
            ->route('teams.show', $teamId)
            ->with('success', 'CSV uploaded — import is processing.');
    }
}
