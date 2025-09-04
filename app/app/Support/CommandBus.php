<?php

namespace App\Support;

use App\Models\User;

class CommandBus
{
    public static function dispatch(string $action, array $params, User $user): array
    {
        $handlerClass = config("command-bus.{$action}");

        if (! $handlerClass) {
            throw new \InvalidArgumentException("Unknown action: {$action}");
        }

        return app($handlerClass)->handle($params, $user);
    }
}
