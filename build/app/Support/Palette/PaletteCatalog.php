<?php

namespace App\Support\Palette;

use App\Contracts\DescribesPalette;
use App\Http\Controllers\CommandController;
use App\Models\Company;
use App\Models\User;
use App\Support\Palette\Commands\GenericCommand;
use App\Support\Palette\Commands\PaletteCommand;
use App\Support\Palette\Commands\PaletteCommandRegistry;
use App\Services\CommandBus;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Every CommandBus action the user may run, described for the palette.
 * Discovery only: execution keeps its own checks. Bound scoped, so the memo
 * lives for one request.
 */
final class PaletteCatalog
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $memo = [];

    public function __construct(
        private readonly CommandBus $bus,
        private readonly RuleIntrospector $introspector,
        private readonly ModuleRegistry $modules,
        private readonly PaletteCommandRegistry $declared,
    ) {}

    /**
     * What the palette fetches when it opens: the declared sentence commands this user
     * may run, and the CommandBus actions as before (until the old palette is retired).
     *
     * @return array{sentences: list<array<string, mixed>>, commands: list<array<string, mixed>>}
     */
    public function payload(User $user, ?Company $company): array
    {
        $sentences = [];
        if ($company !== null) {
            foreach ($this->commands($user, $company) as $command) {
                try {
                    $sentences[] = $command->toCatalog($company);
                } catch (\Throwable $e) {
                    Log::warning('palette_command_skip', ['command' => $command->name(), 'error' => $e->getMessage()]);
                }
            }
        }

        return ['sentences' => $sentences, 'commands' => $this->for($user, $company)];
    }

    /**
     * Declared commands, then a generic one for every catalog entry no declared command targets.
     * A generic list or view has nowhere to go until the palette shows tables, so it is left out.
     *
     * @return list<PaletteCommand>
     */
    public function commands(User $user, ?Company $company): array
    {
        if ($company === null) {
            return [];
        }

        $commands = $this->declared->for($user, $company);
        $targets = array_map(fn (PaletteCommand $c) => $c->target(), $commands);
        foreach ($this->for($user, $company) as $entry) {
            if (! $entry['query'] && ! in_array($entry['key'], $targets, true)) {
                $commands[] = new GenericCommand($entry);
            }
        }

        return $commands;
    }

    /** One command by name. Generic names are `entity.verb` keys, so the catalog is only built for a dotted name. */
    public function find(User $user, ?Company $company, string $name): ?PaletteCommand
    {
        if ($company === null) {
            return null;
        }

        foreach ($this->declared->for($user, $company) as $command) {
            if ($command->name() === $name) {
                return $command;
            }
        }
        if (! str_contains($name, '.')) {
            return null;
        }

        foreach ($this->commands($user, $company) as $command) {
            if ($command->name() === $name) {
                return $command;
            }
        }

        return null;
    }

    /** @return list<array{key: string, entity: string, verb: string, description: string, query: bool, form: bool, form_url: ?string, args: list<array>, flags: list<array>}> */
    public function for(User $user, ?Company $company): array
    {
        $memoKey = $user->id.'|'.($company?->id ?? '-');

        return $this->memo[$memoKey] ??= $this->build($user, $company);
    }

    private function build(User $user, ?Company $company): array
    {
        $entries = [];
        $map = config('command-bus', []);

        foreach ($this->bus->registered() as $key) {
            try {
                if (! str_contains($key, '.') || ! $this->moduleAllows($key, $company)) {
                    continue;
                }

                $handler = app($map[$key]);

                if (! $this->permitted($key, $handler, $user)) {
                    continue;
                }

                $palette = $handler instanceof DescribesPalette ? $handler->palette() : [];
                if (! empty($palette['hidden'])) {
                    continue;
                }

                $entries[] = $this->entry($key, $handler, $palette, $company);
            } catch (\Throwable $e) {
                Log::warning('palette_catalog_skip', ['key' => $key, 'error' => $e->getMessage()]);
            }
        }

        return $entries;
    }

    private function moduleAllows(string $key, ?Company $company): bool
    {
        foreach ($this->modules->all() as $manifest) {
            foreach ($manifest->commandPrefixes() as $prefix) {
                if (str_starts_with($key, $prefix)) {
                    return $company !== null && $manifest->isEnabledFor($company);
                }
            }
        }

        return true;
    }

    private function permitted(string $key, object $handler, User $user): bool
    {
        if (in_array($key, CommandController::GLOBAL_COMMANDS, true)
            || in_array($key, CommandController::COMPANY_TARGET_COMMANDS, true)) {
            return true;
        }

        $permission = $handler->permission();

        return $permission === null || $user->hasCompanyPermission($permission);
    }

    private function entry(string $key, object $handler, array $palette, ?Company $company): array
    {
        $pos = strrpos($key, '.');
        $entity = substr($key, 0, $pos);
        $verb = substr($key, $pos + 1);

        $described = $this->introspector->describe($handler->rules());
        $hints = $palette['fields'] ?? [];

        $fields = array_map(function (array $f) use ($hints) {
            $over = $hints[$f['name']] ?? [];

            return array_merge($f, array_intersect_key($over, array_flip(['hint', 'inputType', 'searchEntity'])));
        }, $described['fields']);

        $args = [];
        $flags = [];
        foreach ($fields as $f) {
            if (! $args && $f['required']) {
                $args[] = $f;
            } else {
                $flags[] = $f;
            }
        }

        return [
            'key' => $key,
            'entity' => $entity,
            'verb' => $verb,
            'description' => $palette['description'] ?? Str::headline($verb).' '.Str::headline(Str::afterLast($entity, '.')),
            'query' => in_array($verb, ['list', 'view'], true) || str_starts_with($key, 'report.'),
            'form' => $described['complex'],
            'form_url' => isset($palette['form_url']) && $company
                ? str_replace('{company}', $company->slug, $palette['form_url'])
                : null,
            'args' => $args,
            'flags' => $flags,
        ];
    }
}
