<?php

namespace Modules\Accounting\Providers;

use Illuminate\Cache\CacheManager;
use Illuminate\Support\ServiceProvider;
use Modules\Accounting\Services\RedisCacheService;

class CacheServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Redis cache service as singleton
        $this->app->singleton(RedisCacheService::class, function ($app) {
            return new RedisCacheService;
        });

        // Extend cache manager to add accounting stores
        $this->app->extend('cache', function (CacheManager $cache, $app) {
            $this->registerAccountingCacheStores($cache);

            return $cache;
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register custom cache stores
        $this->registerCustomStores();
    }

    /**
     * Register accounting cache stores.
     */
    protected function registerAccountingCacheStores(CacheManager $cache): void
    {
        // Register accounting_cache store
        $cache->extend('accounting_cache', function ($app) {
            return $app->make('cache')->repository(
                new \Illuminate\Cache\RedisStore(
                    $app['redis'],
                    config('accounting_redis.connections.cache.prefix'),
                    'accounting_cache'  // Connection name, not array
                )
            );
        });

        // Register accounting_reports store
        $cache->extend('accounting_reports', function ($app) {
            return $app->make('cache')->repository(
                new \Illuminate\Cache\RedisStore(
                    $app['redis'],
                    config('accounting_redis.connections.reports.prefix'),
                    'accounting_reports'  // Connection name, not array
                )
            );
        });

        // Register accounting_session store
        $cache->extend('accounting_session', function ($app) {
            return $app->make('cache')->repository(
                new \Illuminate\Cache\RedisStore(
                    $app['redis'],
                    config('accounting_redis.connections.session.prefix'),
                    'accounting_session'  // Connection name, not array
                )
            );
        });
    }

    /**
     * Register custom stores with Laravel.
     */
    protected function registerCustomStores(): void
    {
        $config = $this->app['config'];

        // Extend database configuration with accounting Redis connections
        $redisConfig = $config->get('database.redis', []);

        // Add accounting connections to Redis config
        foreach (config('accounting_redis.connections', []) as $name => $connection) {
            $redisConfig["accounting_{$name}"] = $connection;
        }

        $config->set('database.redis', $redisConfig);

        // Update cache stores
        $cacheStores = $config->get('cache.stores', []);

        $cacheStores['accounting_cache'] = [
            'driver' => 'redis',
            'connection' => 'accounting_cache',
        ];

        $cacheStores['accounting_reports'] = [
            'driver' => 'redis',
            'connection' => 'accounting_reports',
        ];

        $config->set('cache.stores', $cacheStores);

        // Update queue connections
        $queueConnections = $config->get('queue.connections', []);

        $queueConnections['accounting'] = [
            'driver' => 'redis',
            'connection' => 'accounting',
            'queue' => env('ACCOUNTING_QUEUE', 'accounting_default'),
            'retry_after' => 90,
            'after_commit' => false,
        ];

        $config->set('queue.connections', $queueConnections);
    }
}
