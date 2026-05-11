<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FixtureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $week
 * @property int $home_team_id
 * @property int $away_team_id
 * @property int|null $home_goals
 * @property int|null $away_goals
 * @property \Illuminate\Support\Carbon|null $played_at
 *
 * Class is named "Fixture" rather than "Match" because `match` is a reserved
 * keyword in PHP 8+. The DB table follows the model: `fixtures`.
 */
final class Fixture extends Model
{
    /** @use HasFactory<FixtureFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'week',
        'home_team_id',
        'away_team_id',
        'home_goals',
        'away_goals',
        'played_at',
    ];

    protected $casts = [
        'tournament_id' => 'integer',
        'week' => 'integer',
        'home_team_id' => 'integer',
        'away_team_id' => 'integer',
        'home_goals' => 'integer',
        'away_goals' => 'integer',
        'played_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function homeTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'home_team_id');
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function awayTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'away_team_id');
    }
}
