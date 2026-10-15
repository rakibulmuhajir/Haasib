<?php

namespace App\Modules\FuelStation;

use App\Support\Modules\ModuleManifest;

class FuelStationModule extends ModuleManifest
{
    public function key(): string { return 'fuel_station'; }
    public function industryCodes(): array { return ['fuel_station']; }
    public function requires(): array { return ['inventory']; }
    public function capabilities(): array { return ['daily_close']; }
    public function labels(): array { return ['consolidated.item' => 'Fuel', 'consolidated.quantity' => 'Litres']; }
}
