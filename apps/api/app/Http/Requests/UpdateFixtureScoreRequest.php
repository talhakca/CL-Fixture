<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the manual-score-edit endpoint.
 *
 * Goals are bounded at 20 — a generous ceiling that filters out absurd
 * input but doesn't pretend to legislate football reality. (The simulator
 * itself will essentially never produce > 7 from λ ≤ 3.)
 */
final class UpdateFixtureScoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'home_goals' => ['required', 'integer', 'min:0', 'max:20'],
            'away_goals' => ['required', 'integer', 'min:0', 'max:20'],
        ];
    }

    public function homeGoals(): int
    {
        return (int) $this->validated('home_goals');
    }

    public function awayGoals(): int
    {
        return (int) $this->validated('away_goals');
    }
}
