<?php

/*
|--------------------------------------------------------------------------
| Pest Configuration
|--------------------------------------------------------------------------
|
| Here you may configure Pest's behavior. Feel free to add any custom
| code to this file to fine-tune the test suite's configuration.
|
*/

use Pest\TestSuite;

TestSuite::configure()
    ->stopOnFailure(false)
    ->usePath(__DIR__)
    ->testsPath('tests');

// Note: The RefreshDatabase trait in Laravel tests can cause Pest v4 to show
// "risky" test warnings due to error handler changes during database operations.
// This is a known issue and does not affect test validity.