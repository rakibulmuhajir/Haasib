<?php

// Custom PHPUnit bootstrap for Postgres testing: reset schemas then load autoloader

$isPgsql = getenv('DB_CONNECTION') === 'pgsql';

if ($isPgsql) {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '5432';
    $db = getenv('DB_DATABASE') ?: 'postgres';
    $user = getenv('DB_USERNAME') ?: 'postgres';
    $pass = getenv('DB_PASSWORD') ?: '';

    try {
        $dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $db);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // Drop and recreate schemas to avoid duplicate-table errors across runs
        $sql = <<<'SQL'
DROP SCHEMA IF EXISTS public CASCADE;
CREATE SCHEMA public;
DROP SCHEMA IF EXISTS auth CASCADE;
CREATE SCHEMA auth;
DROP SCHEMA IF EXISTS app CASCADE;
CREATE SCHEMA app;
SQL;
        $pdo->exec($sql);
        
        // Run migrations after schema reset
        $workingDir = dirname(__DIR__);
        $oldCwd = getcwd();
        chdir($workingDir);
        
        // Set environment for test database
        $_ENV['DB_DATABASE'] = 'haasib_test3';
        $_SERVER['DB_DATABASE'] = 'haasib_test3';
        putenv("DB_DATABASE=haasib_test3");
        
        // Run migrations silently
        $migrateOutput = [];
        $migrateReturnCode = 0;
        exec('./artisan migrate --force 2>&1', $migrateOutput, $migrateReturnCode);
        
        if ($migrateReturnCode !== 0) {
            fwrite(STDERR, "[bootstrap_pgsql] Migration failed: " . implode("\n", $migrateOutput) . "\n");
        }
        
        chdir($oldCwd);
    } catch (Throwable $e) {
        // Non-fatal: allow tests to proceed; failures will surface clearly
        fwrite(STDERR, "[bootstrap_pgsql] Schema reset skipped: {$e->getMessage()}\n");
    }
}

// Finally, load the Composer autoloader
require __DIR__.'/../vendor/autoload.php';
