<?php

namespace Modules\Accounting\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Accounting\Models\Company;
use Modules\Accounting\Models\Module;
use Modules\Accounting\Models\User;

class ModuleEnabled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Company $company,
        public Module $module,
        public User $enabledBy,
        public array $settings = []
    ) {}
}
