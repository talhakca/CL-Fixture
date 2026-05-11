<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
| Both Unit and Feature suites bind to TestCase + RefreshDatabase. RefreshDatabase
| keeps the schema between tests but rolls back transactions, so suites stay
| fast while always starting from a clean state.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');
