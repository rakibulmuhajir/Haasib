<?php

namespace App\Modules\Umrah;

use App\Support\Modules\ModuleManifest;

class UmrahModule extends ModuleManifest
{
    public function key(): string { return 'umrah'; }
    public function industryCodes(): array { return ['umrah', 'travel']; }
    public function conflicts(): array { return ['fuel_station']; }
}
