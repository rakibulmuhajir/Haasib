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

        // Check if schemas exist, only create if missing
        $checkSchemaSql = <<<'SQL'
SELECT schema_name FROM information_schema.schemata
WHERE schema_name IN ('public', 'auth', 'app');
SQL;
        $stmt = $pdo->query($checkSchemaSql);
        $existingSchemas = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $missingSchemas = array_diff(['public', 'auth', 'app'], $existingSchemas);

        if (!empty($missingSchemas)) {
            // Create only missing schemas
            foreach ($missingSchemas as $schema) {
                $pdo->exec("CREATE SCHEMA $schema");
            }

            // Run migrations only once when schemas are created
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

            // Run basic seeders once (non-destructive)
            $seedOutput = [];
            $seedReturnCode = 0;
            exec('./artisan db:seed --class=RbacSeeder --force 2>&1', $seedOutput, $seedReturnCode);

            if ($seedReturnCode !== 0) {
                fwrite(STDERR, "[bootstrap_pgsql] RbacSeeder failed: " . implode("\n", $seedOutput) . "\n");
            }

            chdir($oldCwd);
        } else {
            // Set environment for test database
            $_ENV['DB_DATABASE'] = 'haasib_test3';
            $_SERVER['DB_DATABASE'] = 'haasib_test3';
            putenv("DB_DATABASE=haasib_test3");
        }
      } catch (Throwable $e) {
        // Non-fatal: allow tests to proceed; failures will surface clearly
        fwrite(STDERR, "[bootstrap_pgsql] Schema reset skipped: {$e->getMessage()}\n");
    }
}

// Finally, load the Composer autoloader
require __DIR__.'/../vendor/autoload.php';
