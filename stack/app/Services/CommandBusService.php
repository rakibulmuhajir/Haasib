<?php

namespace App\Services;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use App\Traits\AuditLogging;

class CommandBusService extends BaseService
{
    use AuditLogging;

    private array $commandMap;

    public function __construct(ServiceContext $context)
    {
        parent::__construct($context);
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

        // Create comprehensive audit entry for command execution
        $this->audit('command_bus.execution_started', [
            'command' => $command,
            'action_class' => $actionClass,
            'executed_by_user_id' => $this->getUserId(),
            'company_id' => $this->getCompanyId(),
            'parameters_count' => count($parameters),
            'sanitized_parameters' => $this->sanitizeParameters($parameters),
            'request_id' => $this->getRequestId(),
        ]);

        try {
            $result = $action->execute(...array_values($parameters));

            // Create audit entry for successful execution
            $this->audit('command_bus.execution_succeeded', [
                'command' => $command,
                'action_class' => $actionClass,
                'executed_by_user_id' => $this->getUserId(),
                'company_id' => $this->getCompanyId(),
                'request_id' => $this->getRequestId(),
            ]);

            return $result;
        } catch (\Exception $e) {
            // Create audit entry for failed execution
            $this->audit('command_bus.execution_failed', [
                'command' => $command,
                'action_class' => $actionClass,
                'executed_by_user_id' => $this->getUserId(),
                'company_id' => $this->getCompanyId(),
                'error_message' => $e->getMessage(),
                'error_class' => get_class($e),
                'request_id' => $this->getRequestId(),
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
        return $this->executeInTransaction(function () use ($commands) {
            $results = [];
            $executedCommands = [];
            $transactionId = $this->generateTransactionId();

            // Create audit entry for transaction start
            $this->audit('command_bus.transaction_started', [
                'transaction_id' => $transactionId,
                'commands_count' => count($commands),
                'executed_by_user_id' => $this->getUserId(),
                'company_id' => $this->getCompanyId(),
                'command_list' => array_column($commands, 'command'),
                'request_id' => $this->getRequestId(),
            ]);

            try {
                foreach ($commands as $index => $commandData) {
                    $commandName = $commandData['command'];
                    $parameters = $commandData['parameters'] ?? [];

                    $result = $this->execute($commandName, $parameters);
                    
                    $results[$index] = $result;
                    $executedCommands[] = $commandName;
                }

                // Create audit entry for successful transaction
                $this->audit('command_bus.transaction_succeeded', [
                    'transaction_id' => $transactionId,
                    'executed_commands_count' => count($executedCommands),
                    'executed_by_user_id' => $this->getUserId(),
                    'company_id' => $this->getCompanyId(),
                    'executed_commands' => $executedCommands,
                    'request_id' => $this->getRequestId(),
                ]);

                return [
                    'success' => true,
                    'results' => $results,
                    'executed_commands' => $executedCommands,
                    'transaction_id' => $transactionId,
                ];
            } catch (\Exception $e) {
                // Create audit entry for failed transaction
                $this->audit('command_bus.transaction_failed', [
                    'transaction_id' => $transactionId,
                    'executed_commands_count' => count($executedCommands),
                    'failed_at_command' => $commands[array_key_last($results) + 1]['command'] ?? 'unknown',
                    'executed_by_user_id' => $this->getUserId(),
                    'company_id' => $this->getCompanyId(),
                    'error_message' => $e->getMessage(),
                    'executed_commands' => $executedCommands,
                    'request_id' => $this->getRequestId(),
                ]);

                return [
                    'success' => false,
                    'results' => $results,
                    'executed_commands' => $executedCommands,
                    'error' => $e->getMessage(),
                    'transaction_id' => $transactionId,
                ];
            }
        });
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
     * Generate a unique transaction ID
     */
    private function generateTransactionId(): string
    {
        return 'cmd_tx_' . uniqid() . '_' . time();
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

        // Create audit entry for metadata access
        $this->audit('command_bus.metadata_accessed', [
            'metadata_commands_count' => count($metadata),
            'accessed_by_user_id' => $this->getUserId(),
            'company_id' => $this->getCompanyId(),
            'request_id' => $this->getRequestId(),
        ]);

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