<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tournament;
use App\Repositories\Interfaces\TournamentRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tournament lifecycle. Creating a tournament also generates its full
 * schedule in one transactional step — there is no "tournament without
 * fixtures" state in the system.
 */
final class TournamentService
{
    public function __construct(
        private readonly TournamentRepositoryInterface $tournaments,
        private readonly FixtureGeneratorService $generator,
    ) {}

    /**
     * @return Collection<int, Tournament>
     */
    public function list(): Collection
    {
        return $this->tournaments->all();
    }

    public function findOrFail(int $id): Tournament
    {
        return $this->tournaments->findOrFail($id);
    }

    /**
     * @param  array<string, mixed>|null  $settings  per-tournament overrides for game.* keys
     */
    public function create(?string $name = null, ?array $settings = null): Tournament
    {
        return DB::transaction(function () use ($name, $settings): Tournament {
            $tournament = $this->tournaments->create([
                'name' => $name ?? 'Season '.$this->tournaments->nextSeasonNumber(),
                'settings' => $settings,
                'started_at' => now(),
            ]);

            $this->generator->generate($tournament);

            return $tournament;
        });
    }

    public function delete(Tournament $tournament): void
    {
        // Cascade on FK takes care of fixtures + predictions.
        $this->tournaments->delete($tournament);
    }
}
