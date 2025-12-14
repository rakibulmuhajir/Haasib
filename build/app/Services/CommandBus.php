<?php

namespace App\Services;

use App\Contracts\PaletteAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

class CommandBus
{
    /**
     * @var array<string, class-string>
     */
    private array $map;

    public function __construct(?array $map = null)
    {
        $this->map = $map ?? (config('command-bus') ?? config('command_bus', []));

        if (empty($this->map)) {
            throw new \RuntimeException('Command bus configuration missing. Ensure config/command-bus.php is published.');
        }
    }

    /**
     * Dispatch a command by name.
     */
    public function dispatch(string $action, array $params = [], $user = null, bool $skipPermission = false): array
    {
        $handler = $this->resolveHandler($action);

        $rules = $handler->rules();
        if (! empty($rules)) {
            $params = Validator::make($params, $rules)->validate();
        }

        $permission = $handler->permission();
        if ($permission && ! $skipPermission && $user && method_exists($user, 'hasCompanyPermission')) {
            if (! $user->hasCompanyPermission($permission)) {
                throw new AuthorizationException("Permission denied: {$permission}");
            }
        }

        return $handler->handle($params);
    }

    /**
     * List registered command names.
     *
     * @return array<int, string>
     */
    public function registered(): array
    {
        return array_keys($this->map);
    }

    /**
     * Check if a command is registered and its handler class is loadable.
     */
    public function has(string $action): bool
    {
        return isset($this->map[$action]) && class_exists($this->map[$action]);
    }

    private function resolveHandler(string $action): PaletteAction
    {
        $class = $this->map[$action] ?? null;
        if (! $class) {
            throw new InvalidArgumentException("Unknown command: {$action}");
        }

        if (! class_exists($class)) {
            throw new InvalidArgumentException("Command handler class not found: {$class}");
        }

        $handler = app($class);
        if (! $handler instanceof PaletteAction) {
            throw new InvalidArgumentException("Invalid handler for command: {$action}");
        }

        return $handler;
    }
}
