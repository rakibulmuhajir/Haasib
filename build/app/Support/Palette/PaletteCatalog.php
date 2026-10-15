<?php

namespace App\Support\Palette;

use App\Contracts\DescribesPalette;
use App\Http\Controllers\CommandController;
use App\Models\Company;
use App\Models\User;
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
    ) {}

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

                $entries[] = $this->entry($key, $handler, $palette);
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

    private function entry(string $key, object $handler, array $palette): array
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
            'form_url' => $palette['form_url'] ?? null,
            'args' => $args,
            'flags' => $flags,
        ];
    }
}
