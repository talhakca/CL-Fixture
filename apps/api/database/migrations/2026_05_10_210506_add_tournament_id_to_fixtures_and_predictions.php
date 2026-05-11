<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Existing fixture/prediction rows from the pre-Tournament era
        // can't satisfy a NOT NULL FK retroactively — wipe them. This is
        // dev/test data; production has nothing here yet.
        DB::table('predictions')->delete();
        DB::table('fixtures')->delete();

        Schema::table('fixtures', function (Blueprint $table): void {
            $table->foreignId('tournament_id')
                ->after('id')
                ->constrained('tournaments')
                ->cascadeOnDelete();
        });

        Schema::table('predictions', function (Blueprint $table): void {
            $table->foreignId('tournament_id')
                ->after('id')
                ->constrained('tournaments')
                ->cascadeOnDelete();

            // Drop the old (week, team_id) unique — predictions are now
            // unique per (tournament, week, team).
            $table->dropUnique(['week', 'team_id']);
            $table->unique(['tournament_id', 'week', 'team_id']);
        });
    }

    public function down(): void
    {
        Schema::table('predictions', function (Blueprint $table): void {
            $table->dropUnique(['tournament_id', 'week', 'team_id']);
            $table->unique(['week', 'team_id']);
            $table->dropConstrainedForeignId('tournament_id');
        });

        Schema::table('fixtures', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tournament_id');
        });
    }
};
