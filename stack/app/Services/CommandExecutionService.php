<?php

namespace App\Services;

use App\Models\AuditEntry;
use App\Models\Command;
use App\Models\CommandExecution;
use App\Models\CommandHistory;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class CommandExecutionService
{
    private CommandRegistryService $commandRegistry;

    public function __construct(CommandRegistryService $commandRegistry)
    {
        $this->commandRegistry = $commandRegistry;
    }

    public function executeCommand(
        Company $company,
        User $user,
        string $commandName,
        array $parameters = [],
        ?string $idempotencyKey = null
    ): array {
        return DB::transaction(function () use ($company, $user, $commandName, $parameters, $idempotencyKey) {
            $command = $this->commandRegistry->getCommandByName($company, $commandName);

            if (! $command) {
                throw new \InvalidArgumentException("Command '{$commandName}' not found");
            }

            if (! $command->userHasPermission($user)) {
                throw new \InvalidArgumentException("You don't have permission to execute this command");
            }

            // Check for idempotency collision
            if ($idempotencyKey) {
                $existingExecution = $this->findExistingExecution($company, $idempotencyKey);
                if ($existingExecution) {
                    return $this->handleIdempotencyCollision($existingExecution);
                }
            }

            // Validate parameters
            $this->validateParameters($command, $parameters);

            // Create execution record
            $execution = $this->createExecution($command, $user, $company, $parameters, $idempotencyKey);

            try {
                // Execute the command through the command bus
                $result = $this->dispatchCommand($command, $parameters, $user, $company);

                // Update execution with success
                $this->completeExecution($execution, $result, null);

                // Create audit log entry
                $this->createAuditLogEntry($command, $user, $company, $parameters, $result, $execution->audit_reference);

                // Create history record
                $this->createHistoryRecord($command, $user, $company, $parameters, $result);

                return [
                    'success' => true,
                    'execution_id' => $execution->id,
                    'result' => $result,
                    'audit_reference' => $execution->audit_reference,
                ];

            } catch (\Exception $e) {
                // Update execution with failure
                $this->failExecution($execution, $e);

                // Create audit log entry for failure
                $this->createAuditLogEntry($command, $user, $company, $parameters, null, $execution->audit_reference, $e);

                // Create history record
                $this->createHistoryRecord($command, $user, $company, $parameters, null, $e->getMessage());

                Log::error('Command execution failed', [
                    'command' => $commandName,
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'error' => $e->getMessage(),
                ]);

                return [
                    'success' => false,
                    'execution_id' => $execution->id,
                    'error' => $e->getMessage(),
                ];
            }
        });
    }

    public function executeBatchCommands(
        Company $company,
        User $user,
        array $commands
    ): array {
        $results = [];
        $errors = [];

        foreach ($commands as $index => $commandData) {
            try {
                $result = $this->executeCommand(
                    $company,
                    $user,
                    $commandData['name'],
                    $commandData['parameters'] ?? [],
                    $commandData['idempotency_key'] ?? null
                );

                $results[$index] = $result;
            } catch (\Exception $e) {
                $errors[$index] = [
                    'command' => $commandData['name'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'total' => count($commands),
            'successful' => count($results),
            'failed' => count($errors),
            'results' => $results,
            'errors' => $errors,
        ];
    }

    public function getExecutionStatus(string $executionId): ?CommandExecution
    {
        return CommandExecution::find($executionId);
    }

    private function findExistingExecution(Company $company, string $idempotencyKey): ?CommandExecution
    {
        return CommandExecution::query()
            ->where('company_id', $company->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function handleIdempotencyCollision(CommandExecution $existingExecution): array
    {
        if ($existingExecution->status === 'completed') {
            return [
                'success' => true,
                'execution_id' => $existingExecution->id,
                'result' => $existingExecution->result,
                'audit_reference' => $existingExecution->audit_reference,
                'idempotency_collision' => true,
            ];
        }

        if ($existingExecution->status === 'failed') {
            return [
                'success' => false,
                'execution_id' => $existingExecution->id,
                'error' => $existingExecution->error_message,
                'idempotency_collision' => true,
            ];
        }

        // Still running
        return [
            'success' => false,
            'execution_id' => $existingExecution->id,
            'error' => 'Command is already running',
            'idempotency_collision' => true,
        ];
    }

    private function validateParameters(Command $command, array $parameters): void
    {
        $requiredParams = array_filter($command->parameters, fn ($param) => $param['required'] ?? false);

        foreach ($requiredParams as $param) {
            $paramName = $param['name'];
            if (! array_key_exists($paramName, $parameters)) {
                throw new \InvalidArgumentException("Required parameter '{$paramName}' is missing");
            }
        }

        // Type validation would go here based on parameter types
        foreach ($parameters as $name => $value) {
            $paramDef = collect($command->parameters)->firstWhere('name', $name);
            if ($paramDef) {
                $this->validateParameterType($name, $value, $paramDef);
            }
        }
    }

    private function validateParameterType(string $name, mixed $value, array $paramDef): void
    {
        $expectedType = $paramDef['type'] ?? 'mixed';

        if ($expectedType === 'string' && ! is_string($value)) {
            throw new \InvalidArgumentException("Parameter '{$name}' must be a string");
        }

        if ($expectedType === 'int' && ! is_int($value)) {
            throw new \InvalidArgumentException("Parameter '{$name}' must be an integer");
        }

        if ($expectedType === 'bool' && ! is_bool($value)) {
            throw new \InvalidArgumentException("Parameter '{$name}' must be a boolean");
        }

        if ($expectedType === 'array' && ! is_array($value)) {
            throw new \InvalidArgumentException("Parameter '{$name}' must be an array");
        }
    }

    private function createExecution(
        Command $command,
        User $user,
        Company $company,
        array $parameters,
        ?string $idempotencyKey
    ): CommandExecution {
        $execution = CommandExecution::create([
            'command_id' => $command->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
            'idempotency_key' => $idempotencyKey ?? Str::uuid()->toString(),
            'status' => 'running',
            'started_at' => now(),
            'parameters' => $parameters,
            'audit_reference' => $this->generateAuditReference(),
        ]);

        return $execution;
    }

    private function dispatchCommand(Command $command, array $parameters, User $user, Company $company): mixed
    {
        $actionClass = config("command-bus.{$command->name}");

        if (! $actionClass || ! class_exists($actionClass)) {
            throw new \RuntimeException("Action class for command '{$command->name}' not found");
        }

        $action = new $actionClass;

        // Prepare parameters for the action
        $actionParams = $this->prepareActionParameters($action, $parameters, $user, $company);

        return $action->handle(...$actionParams);
    }

    private function prepareActionParameters(object $action, array $parameters, User $user, Company $company): array
    {
        $reflection = new \ReflectionClass($action);
        $method = $reflection->getMethod('handle');
        $actionParams = [];

        foreach ($method->getParameters() as $param) {
            $paramName = $param->getName();
            $paramType = $param->getType()?->getName();

            if ($paramType === User::class) {
                $actionParams[] = $user;
            } elseif ($paramType === Company::class) {
                $actionParams[] = $company;
            } elseif (array_key_exists($paramName, $parameters)) {
                $actionParams[] = $parameters[$paramName];
            } elseif ($param->isDefaultValueAvailable()) {
                $actionParams[] = $param->getDefaultValue();
            } else {
                throw new \InvalidArgumentException("Missing required parameter: {$paramName}");
            }
        }

        return $actionParams;
    }

    private function completeExecution(CommandExecution $execution, mixed $result, ?string $error): void
    {
        $execution->update([
            'status' => 'completed',
            'completed_at' => now(),
            'result' => $result,
            'error_message' => $error,
        ]);
    }

    private function failExecution(CommandExecution $execution, \Exception $e): void
    {
        $execution->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $e->getMessage(),
        ]);
    }

    private function createHistoryRecord(
        Command $command,
        User $user,
        Company $company,
        array $parameters,
        mixed $result = null,
        ?string $error = null
    ): void {
        CommandHistory::create([
            'user_id' => $user->id,
            'command_id' => $command->id,
            'company_id' => $company->id,
            'executed_at' => now(),
            'input_text' => $this->generateInputText($command, $parameters),
            'parameters_used' => $parameters,
            'execution_status' => $error ? 'failed' : 'success',
            'result_summary' => $this->generateResultSummary($result, $error),
            'audit_reference' => $this->generateAuditReference(),
        ]);
    }

    private function generateInputText(Command $command, array $parameters): string
    {
        $text = $command->name;

        if (! empty($parameters)) {
            $paramStrs = [];
            foreach ($parameters as $key => $value) {
                $paramStrs[] = "{$key}=".(is_string($value) ? "'{$value}'" : json_encode($value));
            }
            $text .= ' '.implode(' ', $paramStrs);
        }

        return $text;
    }

    private function generateResultSummary(mixed $result, ?string $error): string
    {
        if ($error) {
            return "Error: {$error}";
        }

        if (is_array($result)) {
            if (isset($result['message'])) {
                return $result['message'];
            }
            if (isset($result['success'])) {
                return $result['success'] ? 'Completed successfully' : 'Failed';
            }

            return 'Completed';
        }

        if (is_string($result)) {
            return $result;
        }

        return 'Completed';
    }

    private function createAuditLogEntry(
        Command $command,
        User $user,
        Company $company,
        array $parameters,
        mixed $result = null,
        string $auditReference,
        ?\Exception $exception = null
    ): void {
        AuditEntry::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'event' => $exception ? 'command_failed' : 'command_executed',
            'model_type' => CommandExecution::class,
            'model_id' => $auditReference,
            'old_values' => [],
            'new_values' => [
                'command_name' => $command->name,
                'command_description' => $command->description,
                'parameters' => $parameters,
                'result' => $result,
                'execution_status' => $exception ? 'failed' : 'success',
                'error_message' => $exception?->getMessage(),
            ],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'tags' => [
                'command_palette',
                'command_execution',
                $exception ? 'error' : 'success',
                'command:'.$command->name,
            ],
            'metadata' => [
                'command_id' => $command->id,
                'company_id' => $company->id,
                'user_id' => $user->id,
                'execution_reference' => $auditReference,
                'parameter_count' => count($parameters),
                'execution_timestamp' => now()->toISOString(),
                'financial_operation' => $this->isFinancialOperation($command->name),
            ],
        ]);
    }

    private function isFinancialOperation(string $commandName): bool
    {
        $financialCommands = [
            'invoice.create',
            'invoice.update',
            'invoice.post',
            'invoice.cancel',
            'customer.create',
            'customer.update',
        ];

        return in_array($commandName, $financialCommands);
    }

    private function generateAuditReference(): string
    {
        return Str::uuid()->toString();
    }
}
