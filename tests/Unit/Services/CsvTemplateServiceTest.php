<?php

use App\Services\CsvTemplateService;

describe('CsvTemplateService', function () {

    beforeEach(function () {
        $this->service = new CsvTemplateService;
    });

    describe('generateTemplateContent', function () {
        it('returns the exact header row with a trailing newline', function () {
            $content = $this->service->generateTemplateContent();

            expect($content)->toBe(
                'first_name,last_name,jersey_number,role,height_feet,weight_kg,is_active'."\n"
            );
        });

        it('contains all required roster columns', function () {
            $content = $this->service->generateTemplateContent();
            $columns = explode(',', trim($content));

            expect($columns)->toContain('first_name')
                ->toContain('last_name')
                ->toContain('jersey_number')
                ->toContain('role')
                ->toContain('height_feet')
                ->toContain('weight_kg')
                ->toContain('is_active');
        });

        it('does not include stat columns (roster-only template)', function () {
            $content = $this->service->generateTemplateContent();

            expect($content)->not->toContain('plus_minus')
                ->not->toContain(',pts,')
                ->not->toContain(',blk,');
        });
    });

    describe('headersMatch', function () {
        it('returns true for an exact match', function () {
            expect($this->service->headersMatch(CsvTemplateService::HEADERS))->toBeTrue();
        });

        it('returns false when a column is missing', function () {
            $headers = array_diff(CsvTemplateService::HEADERS, ['is_active']);
            expect($this->service->headersMatch(array_values($headers)))->toBeFalse();
        });

        it('returns false when an extra column is present', function () {
            $headers = [...CsvTemplateService::HEADERS, 'pts'];
            expect($this->service->headersMatch($headers))->toBeFalse();
        });

        it('returns false when column order is wrong', function () {
            $reversed = array_reverse(CsvTemplateService::HEADERS);
            expect($this->service->headersMatch($reversed))->toBeFalse();
        });

        it('returns false when headers differ only by case', function () {
            $upperCase = array_map('strtoupper', CsvTemplateService::HEADERS);
            expect($this->service->headersMatch($upperCase))->toBeFalse();
        });

        it('returns false for an empty array', function () {
            expect($this->service->headersMatch([]))->toBeFalse();
        });
    });
});
