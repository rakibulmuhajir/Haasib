<?php

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class CommandBusService
{
    private array $commandMap;

    public function __construct()
    {
        $this->commandMap = config('command-bus', []);
    }

    /**
     * Execute a command by name
     */
    public function execute(string $command, array $parameters = []): mixed
    {
        if (! isset($this->commandMap[$command])) {
            throw new InvalidArgumentException("Unknown command: {$command}");
        }

        $actionClass = $this->commandMap[$command];

        if (! class_exists($actionClass)) {
            throw new InvalidArgumentException("Action class not found: {$actionClass}");
        }

        $action = App::make($actionClass);

        // Log command execution
        Log::info('Executing command', [
            'command' => $command,
            'action' => $actionClass,
            'user_id' => auth()->id(),
            'parameters' => $this->sanitizeParameters($parameters),
        ]);

        try {
            $result = $action->execute(...array_values($parameters));

            Log::info('Command executed successfully', [
                'command' => $command,
                'user_id' => auth()->id(),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Command execution failed', [
                'command' => $command,
                'action' => $actionClass,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if a command is registered
     */
    public function hasCommand(string $command): bool
    {
        return isset($this->commandMap[$command]);
    }

    /**
     * Get all registered commands
     */
    public function getRegisteredCommands(): array
    {
        return array_keys($this->commandMap);
    }

    /**
     * Get commands for a specific category
     */
    public function getCommandsByCategory(string $category): array
    {
        return array_filter(
            $this->commandMap,
            fn(string $command) => str_starts_with($command, $category . '.'),
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Execute multiple commands in a transaction
     */
    public function executeTransaction(array $commands): array
    {
        $results = [];
        $executedCommands = [];

        try {
            foreach ($commands as $index => $commandData) {
                $commandName = $commandData['command'];
                $parameters = $commandData['parameters'] ?? [];

                $result = $this->execute($commandName, $parameters);
                
                $results[$index] = $result;
                $executedCommands[] = $commandName;
            }

            return [
                'success' => true,
                'results' => $results,
                'executed_commands' => $executedCommands,
            ];
        } catch (\Exception $e) {
            Log::error('Command transaction failed', [
                'executed_commands' => $executedCommands,
                'failed_command' => $commands[array_key_last($results) + 1]['command'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'results' => $results,
                'executed_commands' => $executedCommands,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sanitize parameters for logging
     */
    private function sanitizeParameters(array $parameters): array
    {
        $sensitive = ['password', 'token', 'secret', 'api_key'];
        
        return array_map(function ($param) use ($sensitive) {
            if (is_object($param)) {
                return get_class($param);
            }
            
            if (is_array($param)) {
                return $this->sanitizeParameters($param);
            }
            
            if (in_array(strtolower($param), $sensitive)) {
                return '[REDACTED]';
            }
            
            return $param;
        }, $parameters);
    }

    /**
     * Get command metadata for API documentation
     */
    public function getCommandMetadata(): array
    {
        $metadata = [];

        foreach ($this->commandMap as $command => $actionClass) {
            $metadata[$command] = [
                'action' => $actionClass,
                'description' => $this->getCommandDescription($command, $actionClass),
                'category' => $this->getCommandCategory($command),
            ];
        }

        return $metadata;
    }

    private function getCommandDescription(string $command, string $actionClass): string
    {
        $descriptions = [
            'company.create' => 'Create a new company',
            'company.activate' => 'Activate a company',
            'company.deactivate' => 'Deactivate a company',
            'company.delete' => 'Delete a company',
            'company.invite' => 'Invite a user to join a company',
            'invitation.revoke' => 'Revoke a company invitation',
            'company.assign' => 'Assign a user to a company',
            'company.unassign' => 'Unassign a user from a company',
            'company.update_role' => 'Update a user\'s role in a company',
        ];

        return $descriptions[$command] ?? 'Execute ' . $command;
    }

    private function getCommandCategory(string $command): string
    {
        $parts = explode('.', $command);
        return $parts[0] ?? 'unknown';
    }
}