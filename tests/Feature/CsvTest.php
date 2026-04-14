<?php

use App\Jobs\ProcessCsvImport;
use App\Models\Team;
use App\Models\User;
use App\Services\CsvTemplateService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

describe('CSV endpoints', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        Storage::fake(); // isolated local disk for uploads
    });

    // ------------------------------------------------------------------
    // Template Download
    // ------------------------------------------------------------------
    describe('GET /csv/template', function () {
        it('requires authentication', function () {
            $this->get(route('csv.template'))->assertRedirect(route('login'));
        });

        it('streams the CSV template with correct headers', function () {
            $response = $this->actingAs($this->user)
                ->get(route('csv.template'));

            $response->assertOk();
            $response->assertHeader('content-type', 'text/csv; charset=utf-8');
            $response->assertHeader(
                'content-disposition',
                'attachment; filename=hoopsense-roster-template.csv'
            );
        });

        it('returns the exact roster-only column headers', function () {
            $content = $this->actingAs($this->user)
                ->get(route('csv.template'))
                ->streamedContent();

            $columns = explode(',', trim($content));

            expect($columns)->toBe(CsvTemplateService::HEADERS);
        });
    });

    // ------------------------------------------------------------------
    // CSV Upload
    // ------------------------------------------------------------------
    describe('POST /csv/upload', function () {
        it('requires authentication', function () {
            $this->post(route('csv.upload'))->assertRedirect(route('login'));
        });

        it('dispatches ProcessCsvImport and redirects to team show on valid upload', function () {
            Bus::fake();
            $team = Team::factory()->create();

            $csv = UploadedFile::fake()->createWithContent(
                'roster.csv',
                implode(',', CsvTemplateService::HEADERS) . "\nJohn,Doe,23,Point Guard,6.1,85.0,1\n"
            );

            $this->actingAs($this->user)
                ->post(route('csv.upload'), ['team_id' => $team->id, 'file' => $csv])
                ->assertRedirect(route('teams.show', $team));

            Bus::assertDispatched(ProcessCsvImport::class);
        });

        it('stores the uploaded file and creates a csv_import record', function () {
            Bus::fake();
            $team = Team::factory()->create();

            $csv = UploadedFile::fake()->createWithContent(
                'roster.csv',
                implode(',', CsvTemplateService::HEADERS) . "\nJane,Smith,11,Center,6.4,100.0,1\n"
            );

            $this->actingAs($this->user)
                ->post(route('csv.upload'), ['team_id' => $team->id, 'file' => $csv]);

            $this->assertDatabaseHas('csv_imports', [
                'team_id' => $team->id,
                'status'  => 'pending',
            ]);
        });

        it('rejects upload when team_id is missing', function () {
            $csv = UploadedFile::fake()->createWithContent('roster.csv', 'first_name');

            $this->actingAs($this->user)
                ->post(route('csv.upload'), ['file' => $csv])
                ->assertSessionHasErrors(['team_id']);
        });

        it('rejects upload when no file is provided', function () {
            $team = Team::factory()->create();

            $this->actingAs($this->user)
                ->post(route('csv.upload'), ['team_id' => $team->id])
                ->assertSessionHasErrors(['file']);
        });
    });
});
