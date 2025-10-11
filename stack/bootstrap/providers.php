<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    App\Providers\CommandBusServiceProvider::class,
    
    // Module Service Providers
    Modules\Accounting\Providers\AccountingServiceProvider::class,
];
