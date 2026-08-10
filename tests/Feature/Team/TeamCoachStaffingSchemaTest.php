<?php

namespace Tests\Feature\Team;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeamCoachStaffingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_staffing_tables_include_the_required_columns(): void
    {
        $this->assertTableHasColumns('teams', [
            'id',
            'code',
            'name',
            'logo_path',
            'main_coach_user_id',
            'is_active',
            'created_at',
            'updated_at',
        ]);

        $this->assertTableHasColumns('team_assistant_coaches', [
            'id',
            'team_id',
            'user_id',
            'created_at',
            'updated_at',
        ]);
    }

    public function test_team_staffing_indexes_are_declared(): void
    {
        $this->assertIndexExists('team_assistant_coaches', ['team_id', 'user_id']);
    }

    public function test_team_staffing_foreign_keys_are_declared(): void
    {
        $this->assertForeignKeyExists('teams', 'main_coach_user_id', 'users');
        $this->assertForeignKeyExists('team_assistant_coaches', 'team_id', 'teams');
        $this->assertForeignKeyExists('team_assistant_coaches', 'user_id', 'users');
    }

    /** @param list<string> $columns */
    private function assertTableHasColumns(string $table, array $columns): void
    {
        $existingColumns = Schema::getColumnListing($table);

        foreach ($columns as $column) {
            $this->assertContains($column, $existingColumns, "Expected {$table}.{$column} to exist.");
        }
    }

    /** @param list<string> $columns */
    private function assertIndexExists(string $table, array $columns): void
    {
        $indexExists = collect(Schema::getIndexes($table))
            ->contains(fn (array $index): bool => $index['columns'] === $columns && ($index['unique'] ?? false) === true);

        $this->assertTrue($indexExists, "Expected a unique index on {$table} (".implode(', ', $columns).').');
    }

    private function assertForeignKeyExists(string $table, string $column, string $referencedTable): void
    {
        $foreignKeyExists = collect(Schema::getForeignKeys($table))
            ->contains(fn (array $foreignKey): bool => $foreignKey['columns'] === [$column]
                && $foreignKey['foreign_table'] === $referencedTable);

        $this->assertTrue($foreignKeyExists, "Expected {$table}.{$column} to reference {$referencedTable}.");
    }
}
