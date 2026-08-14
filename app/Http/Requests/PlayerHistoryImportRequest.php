<?php

namespace App\Http\Requests;

use App\Jobs\PlayerHistoryImportJob;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use PhpOffice\PhpSpreadsheet\IOFactory;

class PlayerHistoryImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt,xlsx,vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'max:10240',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A CSV or Excel (.xlsx) file is required.',
            'file.mimes' => 'The upload must be a CSV or Excel (.xlsx) file.',
            'file.max' => 'The file must not exceed 10MB.',
        ];
    }

    /**
     * Validate that the Template sheet's header row matches the required columns.
     * Called after standard validation passes.
     * Returns a human-readable error string, or null if headers are valid.
     */
    public function validateHeaders(): ?string
    {
        $extension = strtolower((string) $this->file('file')?->getClientOriginalExtension());

        if (in_array($extension, ['csv', 'txt'], true)) {
            return $this->validateCsvHeaders();
        }

        return $this->validateSpreadsheetHeaders();
    }

    private function validateCsvHeaders(): ?string
    {
        $handle = fopen($this->file('file')->getRealPath(), 'r');

        if ($handle === false) {
            return 'Could not read the uploaded CSV file. Make sure it is a valid .csv file.';
        }

        try {
            $headerRow = fgetcsv($handle);
        } finally {
            fclose($handle);
        }

        if ($headerRow === false) {
            return 'The uploaded CSV file is empty.';
        }

        $headers = array_map(
            static fn (string $header): string => trim(ltrim($header, "\xEF\xBB\xBF")),
            $headerRow,
        );

        if ($headers !== PlayerHistoryImportJob::HEADERS) {
            return "CSV headers do not match the required template.\n"
                .'Expected: '.implode(', ', PlayerHistoryImportJob::HEADERS)."\n"
                .'Received: '.implode(', ', $headers);
        }

        return null;
    }

    private function validateSpreadsheetHeaders(): ?string
    {
        try {
            $spreadsheet = IOFactory::load($this->file('file')->getRealPath());
        } catch (\Throwable) {
            return 'Could not read the uploaded Excel file. Make sure it is a valid .xlsx file.';
        }

        $sheet = $spreadsheet->getSheetByName('Template') ?? $spreadsheet->getActiveSheet();

        $headerRow = [];

        foreach (range(1, count(PlayerHistoryImportJob::HEADERS)) as $col) {
            $headerRow[] = trim((string) $sheet->getCell([$col, 1])->getValue());
        }

        if ($headerRow !== PlayerHistoryImportJob::HEADERS) {
            return "Excel headers do not match the required template.\n"
                .'Expected: '.implode(', ', PlayerHistoryImportJob::HEADERS)."\n"
                .'Received: '.implode(', ', $headerRow);
        }

        return null;
    }
}
