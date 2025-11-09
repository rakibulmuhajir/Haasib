<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\CommandBusServiceProvider::class,
    App\Providers\CommandPaletteServiceProvider::class,
    App\Providers\CustomerRouteBindingProvider::class,
    App\Providers\EventServiceProvider::class,
    Modules\Accounting\Providers\AccountingServiceProvider::class,
];
