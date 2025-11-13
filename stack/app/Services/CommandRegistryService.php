<?php

namespace App\Services;

use App\Models\Command;
use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CommandRegistryService extends BaseService
{
    use AuditLogging;

    private array $commandCache = [];

    private string $cacheKey;

    public function __construct(ServiceContext $context)
    {
        parent::__construct($context);
        // Company-isolated cache key to prevent cross-tenant data leakage
        $this->cacheKey = 'command_registry_' . ($this->getCompanyId() ?? 'system');
        $this->loadCommandRegistry();
    }

    public function synchronizeCommands(Company $company): void
    {
        // Validate company access
        $this->validateCompanyAccess($company->id);
        
        // Set RLS context
        $this->setRlsContext($company->id);
        
        $registryCommands = $this->getCommandBusRegistry();
        $synchronizedCommands = [];

        foreach ($registryCommands as $name => $actionClass) {
            $metadata = $this->generateCommandMetadata($name, $actionClass);

            Command::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $name,
                ],
                [
                    'description' => $metadata['description'],
                    'parameters' => $metadata['parameters'],
                    'required_permissions' => $metadata['permissions'],
                    'is_active' => true,
                ]
            );
            
            $synchronizedCommands[] = $name;
        }

        $this->clearCache();
        
        // Create audit entry for synchronization
        $this->audit('command_registry.synchronized', [
            'company_id' => $company->id,
            'synchronized_commands_count' => count($synchronizedCommands),
            'synchronized_commands' => $synchronizedCommands,
            'synchronized_by_user_id' => $this->getUserId(),
            'request_id' => $this->getRequestId(),
        ]);
    }

    public function getAvailableCommands(Company $company): \Illuminate\Database\Eloquent\Collection
    {
        // Validate company access
        $this->validateCompanyAccess($company->id);
        
        // Set RLS context
        $this->setRlsContext($company->id);
        
        $commands = Command::query()
            ->where('company_id', $company->id)
            ->active()
            ->orderBy('name')
            ->get();

        // Create audit entry for commands access
        $this->audit('command_registry.commands_accessed', [
            'company_id' => $company->id,
            'commands_count' => $commands->count(),
            'accessed_by_user_id' => $this->getUserId(),
            'request_id' => $this->getRequestId(),
        ]);

        return $commands;
    }

    public function getCommandByName(Company $company, string $name): ?Command
    {
        return Command::query()
            ->where('company_id', $company->id)
            ->where('name', $name)
            ->active()
            ->first();
    }

    public function validateCommandSchema(array $commandData): array
    {
        $errors = [];

        if (empty($commandData['name'])) {
            $errors[] = 'Command name is required';
        }

        if (empty($commandData['description'])) {
            $errors[] = 'Command description is required';
        }

        if (! is_array($commandData['parameters'] ?? null)) {
            $errors[] = 'Parameters must be an array';
        }

        if (! is_array($commandData['required_permissions'] ?? null)) {
            $errors[] = 'Required permissions must be an array';
        }

        return $errors;
    }

    private function getCommandBusRegistry(): array
    {
        return config('command-bus', []);
    }

    private function generateCommandMetadata(string $name, string $actionClass): array
    {
        if (! class_exists($actionClass)) {
            Log::warning('Action class not found', ['class' => $actionClass]);

            return $this->getDefaultMetadata($name);
        }

        try {
            $reflection = new \ReflectionClass($actionClass);
            $method = $reflection->getMethod('handle');

            $parameters = $this->extractParametersFromMethod($method);
            $permissions = $this->extractPermissionsFromClass($actionClass);

            return [
                'description' => $this->generateDescription($name),
                'parameters' => $parameters,
                'permissions' => $permissions,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to generate metadata', ['command' => $name, 'error' => $e->getMessage()]);

            return $this->getDefaultMetadata($name);
        }
    }

    private function extractParametersFromMethod(\ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $param) {
            $paramData = [
                'name' => $param->getName(),
                'type' => $this->getParameterType($param),
                'required' => ! $param->isDefaultValueAvailable(),
            ];

            if ($param->isDefaultValueAvailable()) {
                $paramData['default'] = $param->getDefaultValue();
            }

            $parameters[] = $paramData;
        }

        return $parameters;
    }

    private function getParameterType(\ReflectionParameter $param): string
    {
        $type = $param->getType();

        if ($type instanceof \ReflectionNamedType) {
            return $type->getName();
        }

        return 'mixed';
    }

    private function extractPermissionsFromClass(string $actionClass): array
    {
        $permissions = [];

        if (method_exists($actionClass, 'getRequiredPermissions')) {
            $permissions = $actionClass::getRequiredPermissions();
        }

        if (empty($permissions)) {
            $permissions = $this->inferPermissionsFromClass($actionClass);
        }

        return $permissions;
    }

    private function inferPermissionsFromClass(string $actionClass): array
    {
        $className = class_basename($actionClass);

        if (str_contains($className, 'User')) {
            return ['manage users'];
        }

        if (str_contains($className, 'Customer')) {
            return ['manage customers'];
        }

        if (str_contains($className, 'Invoice')) {
            return ['manage invoices'];
        }

        if (str_contains($className, 'Company')) {
            return ['manage companies'];
        }

        return [];
    }

    private function generateDescription(string $name): string
    {
        $words = explode('.', $name);
        $verb = ucwords($words[0] ?? 'Execute');
        $entity = ucwords($words[1] ?? 'operation');

        return "{$verb} {$entity}";
    }

    private function getDefaultMetadata(string $name): array
    {
        return [
            'description' => $this->generateDescription($name),
            'parameters' => [],
            'permissions' => [],
        ];
    }

    private function loadCommandRegistry(): void
    {
        $this->commandCache = Cache::remember($this->cacheKey, 3600, function () {
            return $this->getCommandBusRegistry();
        });
    }

    private function clearCache(): void
    {
        Cache::forget($this->cacheKey);
        $this->loadCommandRegistry();
    }
}
