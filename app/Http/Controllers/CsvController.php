<?php

namespace App\Http\Controllers;

use App\Services\CsvTemplateService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvController extends Controller
{
    public function __construct(
        private readonly CsvTemplateService $templateService,
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
}
