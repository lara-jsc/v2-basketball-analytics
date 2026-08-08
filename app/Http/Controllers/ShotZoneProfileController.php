<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShotZoneProfileImportRequest;
use App\Jobs\ShotZoneProfileImportJob;
use App\Models\CsvImport;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;

class ShotZoneProfileController extends Controller
{
    /**
     * Download the CSV template for shot zone profiles.
     */
    public function downloadTemplate(): Response
    {
        $headers = ShotZoneProfileImportJob::HEADERS;
        $exampleRow = [1, 30, 55, 12, 30, 5, 13, 6, 15, 18, 50];

        $lines = [
            implode(',', $headers),
            implode(',', $exampleRow),
        ];

        $csv = implode("\n", $lines);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="shot-zone-profile-template.csv"',
        ]);
    }

    /**
     * Handle an uploaded shot zone profile CSV for a team.
     */
    public function import(ShotZoneProfileImportRequest $request, Team $team): RedirectResponse
    {
        $path = $request->file('file')->store('shot-zone-profile-imports');

        /** @var CsvImport $csvImport */
        $csvImport = CsvImport::create([
            'team_id' => $team->id,
            'filename' => $path,
            'status' => CsvImport::STATUS_PENDING,
        ]);

        ShotZoneProfileImportJob::dispatchSync($csvImport->id, $team->id);

        Inertia::flash('success', 'Shot zone profile import complete.');

        return back();
    }
}
