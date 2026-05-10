<?php

namespace App\Http\Controllers;

class HealthController extends Controller
{
    /**
     * Liveness probe.
     *
     * @return array{status: string}
     */
    public function __invoke(): array
    {
        return ['status' => 'ok'];
    }
}
