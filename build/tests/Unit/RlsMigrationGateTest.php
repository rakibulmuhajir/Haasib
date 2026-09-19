<?php

use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Env;

test('disabled RLS migrations stay pending and can be applied after opting in', function (string $filename) {
    $environment = Env::getRepository();
    $previous = $environment->get('RLS_ENFORCEMENT');
    $repository = Mockery::mock(MigrationRepositoryInterface::class);
    $resolver = Mockery::mock(ConnectionResolverInterface::class);
    $migrator = new class($repository, $resolver, new Filesystem) extends Migrator
    {
        public int $executed = 0;

        public function apply(string $path): void
        {
            $this->runUp($path, 1, false);
        }

        protected function runMigration($migration, $method)
        {
            // Exercise the real migrator's gate and bookkeeping without issuing DDL.
            $this->executed++;
        }
    };
    $path = dirname(__DIR__, 2).'/database/migrations/'.$filename.'.php';

    try {
        $environment->clear('RLS_ENFORCEMENT');
        $migrator->apply($path);
        $environment->set('RLS_ENFORCEMENT', 'off');
        $migrator->apply($path);
        expect($migrator->executed)->toBe(0);

        // An unexpected log() during either disabled run fails this test.
        $repository->shouldReceive('log')->once()->with($filename, 1);
        $environment->set('RLS_ENFORCEMENT', 'ON');
        $migrator->apply($path);
        expect($migrator->executed)->toBe(1);
    } finally {
        $previous === null
            ? $environment->clear('RLS_ENFORCEMENT')
            : $environment->set('RLS_ENFORCEMENT', $previous);
        Mockery::close();
    }
})->with([
    '2026_09_18_100000_create_application_database_role',
    '2026_09_30_000000_enforce_row_level_security',
]);
