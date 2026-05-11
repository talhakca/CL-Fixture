<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TournamentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

/**
 * @property int $id
 * @property string $name
 * @property array<string, mixed>|null $settings
 * @property \Illuminate\Support\Carbon $started_at
 *
 * Owns its fixtures and predictions. The simulation algorithm reads
 * tunables (base_goals, home_advantage, luck_amplitude, ...) through
 * Tournament::config(), which falls back to config/game.php when the
 * tournament has no override.
 */
final class Tournament extends Model
{
    /** @use HasFactory<TournamentFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'settings',
        'started_at',
    ];

    protected $casts = [
        'settings' => 'array',
        'started_at' => 'datetime',
    ];

    /**
     * @return HasMany<Fixture, $this>
     */
    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }

    /**
     * @return HasMany<Prediction, $this>
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    /**
     * Resolve a game-config value: this tournament's override takes
     * priority over the global default.
     */
    public function config(string $key): mixed
    {
        if (is_array($this->settings) && array_key_exists($key, $this->settings)) {
            return $this->settings[$key];
        }

        return Config::get('game.'.$key);
    }
}
