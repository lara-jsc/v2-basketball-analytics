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
                'mimes:xlsx,vnd.openxmlformats-officedocument.spreadsheetml.sheet',
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
            'file.required' => 'An Excel (.xlsx) file is required.',
            'file.mimes'    => 'The upload must be an Excel (.xlsx) file.',
            'file.max'      => 'The file must not exceed 10MB.',
        ];
    }

    /**
     * Validate that the Template sheet's header row matches the required columns.
     * Called after standard validation passes.
     * Returns a human-readable error string, or null if headers are valid.
     */
    public function validateXlsxHeaders(): ?string
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
                . "Expected: " . implode(', ', PlayerHistoryImportJob::HEADERS) . "\n"
                . "Received: " . implode(', ', $headerRow);
        }

        // Validate optional plus_minus column if present
        $optionalCol   = count(PlayerHistoryImportJob::HEADERS) + 1;
        $optionalValue = strtolower(trim((string) $sheet->getCell([$optionalCol, 1])->getValue()));

        if ($optionalValue !== '' && ! in_array($optionalValue, PlayerHistoryImportJob::PLUS_MINUS_ALIASES, strict: true)) {
            return "Unexpected column after required headers: '{$optionalValue}'. "
                . "Only an optional plus_minus column is allowed here.";
        }

        return null;
    }
}
