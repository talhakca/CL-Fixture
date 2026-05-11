<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $attack_strength
 * @property int $defense_strength
 *
 * Per BACKEND.md: models are thin. They expose fillable fields, casts, and
 * relationships only — no scoring logic, no simulator hooks, no scopes that
 * embed business rules. Anything domain-shaped lives in services.
 */
final class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'attack_strength',
        'defense_strength',
    ];

    protected $casts = [
        'attack_strength' => 'integer',
        'defense_strength' => 'integer',
    ];

    /**
     * Fixtures where this team plays at home.
     *
     * @return HasMany<Fixture, $this>
     */
    public function homeFixtures(): HasMany
    {
        return $this->hasMany(Fixture::class, 'home_team_id');
    }

    /**
     * @return HasMany<Fixture, $this>
     */
    public function awayFixtures(): HasMany
    {
        return $this->hasMany(Fixture::class, 'away_team_id');
    }

    /**
     * @return HasMany<Prediction, $this>
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }
}
