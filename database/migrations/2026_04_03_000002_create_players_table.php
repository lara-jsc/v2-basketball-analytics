<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->unsignedTinyInteger('jersey_number');
            $table->string('role', 50)->nullable();
            $table->decimal('height_feet', 4, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->string('profile_picture_path', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
