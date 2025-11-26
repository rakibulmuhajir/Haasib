<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CommandBusService
{
    public function __construct(private ServiceContext $context)
    {
    }

    /**
     * Dispatch a command by class name or alias.
     *
     * @param  string  $command
     * @param  array<string,mixed>  $payload
     */
    public function dispatch(string $command, array $payload = []): mixed
    {
        $commandClass = config("command-bus.aliases.$command", $command);

        if (! class_exists($commandClass)) {
            Log::error('[CommandBus] Command class not found', ['command' => $command, 'class' => $commandClass]);
            throw new \InvalidArgumentException("Command {$command} not found.");
        }

        $instance = App::make($commandClass);

        if (! method_exists($instance, '__invoke')) {
            throw new \InvalidArgumentException("Command {$commandClass} is not invokable.");
        }

        // Inject context if supported
        if (method_exists($instance, 'withContext')) {
            $instance = $instance->withContext($this->context);
        }

        return $instance(...Arr::wrap($payload));
    }
}
