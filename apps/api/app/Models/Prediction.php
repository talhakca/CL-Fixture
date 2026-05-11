<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PredictionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $week
 * @property int $team_id
 * @property string $championship_probability
 * @property \Illuminate\Support\Carbon $computed_at
 *
 * One row per (tournament, week, team). championship_probability is a
 * decimal(6,5) in [0, 1]; the four teams' values for a given week sum to
 * exactly 1.
 */
final class Prediction extends Model
{
    /** @use HasFactory<PredictionFactory> */
    use HasFactory;

    protected $fillable = [
        'tournament_id',
        'week',
        'team_id',
        'championship_probability',
        'computed_at',
    ];

    protected $casts = [
        'tournament_id' => 'integer',
        'week' => 'integer',
        'team_id' => 'integer',
        'championship_probability' => 'decimal:5',
        'computed_at' => 'datetime',
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
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
