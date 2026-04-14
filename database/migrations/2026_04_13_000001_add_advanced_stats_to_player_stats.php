<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('player_stats', 'eff')) {
                $table->decimal('eff', 6, 2)->nullable()->after('sh_eff');
            }
            if (! Schema::hasColumn('player_stats', 'efg_pct')) {
                $table->decimal('efg_pct', 5, 2)->nullable()->after('eff');
            }
            if (! Schema::hasColumn('player_stats', 'ts_pct')) {
                $table->decimal('ts_pct', 5, 2)->nullable()->after('efg_pct');
            }
        });
    }

    public function down(): void
    {
        Schema::table('player_stats', function (Blueprint $table) {
            $columns = collect(['eff', 'efg_pct', 'ts_pct'])
                ->filter(fn ($col) => Schema::hasColumn('player_stats', $col))
                ->values()
                ->all();

            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
