<?php

// Dynamic PHPUnit bootstrap: prefer SQLite in-memory if available; otherwise use Postgres

// If pdo_sqlite is not available, force Postgres test configuration
if (! extension_loaded('pdo_sqlite')) {
    $env = [
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => getenv('DB_HOST') ?: '127.0.0.1',
        'DB_PORT' => getenv('DB_PORT') ?: '5432',
        'DB_DATABASE' => getenv('DB_DATABASE') ?: 'haasib_test',
        'DB_USERNAME' => getenv('DB_USERNAME') ?: 'superadmin',
        'DB_PASSWORD' => getenv('DB_PASSWORD') ?: 'AcctP@ss',
    ];

    foreach ($env as $k => $v) {
        putenv("{$k}={$v}");
        $_ENV[$k] = $v;
        $_SERVER[$k] = $v;
    }

    // Reset schemas before running tests
    require __DIR__.'/bootstrap_pgsql.php';
}

// Finally, load Composer autoloader
require __DIR__.'/../vendor/autoload.php';
