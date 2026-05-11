<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per match in the season. The full 4-team double round-robin
        // schedule (12 matches across 6 weeks) is generated once and stored
        // here; week-by-week play simply fills in the goal columns.
        Schema::create('fixtures', function (Blueprint $table): void {
            $table->id();

            // Week within the season (1..6 for a 4-team double round-robin).
            // Indexed because every read groups by week.
            $table->unsignedTinyInteger('week')->index();

            // Home / away references. cascadeOnDelete keeps the schema clean
            // if seed data is wiped during dev; in prod teams are immutable.
            $table->foreignId('home_team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('away_team_id')->constrained('teams')->cascadeOnDelete();

            // Goals are nullable until the match is played. Using nullable
            // (rather than 0/0 + a played boolean) makes "not played yet" a
            // first-class state and prevents accidental 0-0 standings entries.
            $table->unsignedTinyInteger('home_goals')->nullable();
            $table->unsignedTinyInteger('away_goals')->nullable();

            // Timestamp of when the match was simulated. Doubles as the
            // "is_played?" check: NULL = pending, set = played.
            $table->timestamp('played_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fixtures');
    }
};
