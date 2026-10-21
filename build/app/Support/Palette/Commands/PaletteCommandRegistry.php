<?php

namespace App\Support\Palette\Commands;

use App\Models\Company;
use App\Models\User;
use App\Services\CommandBus;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Log;

/** Core commands plus those of every module enabled for the company, minus what the user may not run. */
final class PaletteCommandRegistry
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly ModuleRegistry $modules,
    ) {}

    /** @return list<PaletteCommand> */
    public function for(User $user, ?Company $company): array
    {
        $commands = CorePaletteCommands::all();
        if ($company !== null) {
            foreach ($this->modules->enabledFor($company) as $manifest) {
                $commands = array_merge($commands, $manifest->paletteCommands());
            }
        }

        $allowed = [];
        foreach ($commands as $command) {
            try {
                // slots() is read here so a broken declaration is skipped now, not when the catalog is built.
                $command->slots();
                if ($company !== null && ! $command->availableFor($company)) {
                    continue;
                }
                $permission = $this->permissionFor($command);
                if ($permission === null || $user->hasCompanyPermission($permission)) {
                    $allowed[] = $command;
                }
            } catch (\Throwable $e) {
                Log::warning('palette_command_skip', ['command' => $command->name(), 'error' => $e->getMessage()]);
            }
        }

        return $allowed;
    }

    /**
     * The permission that gates a command. A page-only command (a form to open, a report to show)
     * may target a permission key no CommandBus action carries, e.g. 'item.create'.
     */
    private function permissionFor(PaletteCommand $command): ?string
    {
        $target = $command->target();
        if (! $this->bus->has($target) && ($command->formUrl() !== null || $command->query())) {
            return $target;
        }

        return $this->bus->permissionFor($target);
    }
}
