<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\HandlesInvoiceOperations;
use App\Console\Commands\Concerns\ProvidesNaturalLanguageInteraction;
use Illuminate\Console\Command;

abstract class InvoiceBaseCommand extends Command
{
    use HandlesInvoiceOperations, ProvidesNaturalLanguageInteraction;

    /**
     * The name and signature of the console command.
     */
    protected $signature = '';

    /**
     * The console command description.
     */
    protected $description = '';

    /**
     * The current user instance.
     */
    protected ?\App\Models\User $user = null;

    /**
     * The current company instance.
     */
    protected ?\App\Models\Company $company = null;

    /**
     * Default output format for the command.
     */
    protected string $defaultFormat = 'table';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Initialize command context
        $this->initializeContext();

        try {
            // Validate prerequisites
            if (! $this->validatePrerequisites()) {
                return self::FAILURE;
            }

            // Execute the command logic
            return $this->executeCommand();

        } catch (\Throwable $exception) {
            $this->handleServiceException($exception);

            return self::FAILURE;
        }
    }

    /**
     * Initialize command context (user, company, etc.).
     */
    protected function initializeContext(): void
    {
        $this->user = $this->getCurrentUser();
        $this->company = $this->getCurrentCompany();

        if (! $this->user) {
            $this->error('Authentication required. Please run as an authenticated user.');

            return;
        }

        if (! $this->company && $this->requiresCompany()) {
            $this->error('Company context required. Use --company=<id> or set a default company.');

            return;
        }
    }

    /**
     * Execute the specific command logic.
     * Must be implemented by concrete commands.
     */
    abstract protected function executeCommand(): int;

    /**
     * Validate command prerequisites.
     */
    protected function validatePrerequisites(): bool
    {
        // Check if user is authenticated
        if (! $this->user) {
            $this->error('Authentication required.');

            return false;
        }

        // Check company access if required
        if ($this->requiresCompany() && ! $this->company) {
            $this->error('Company context required.');

            return false;
        }

        // Check specific permissions
        if (! $this->validatePermissions()) {
            return false;
        }

        return true;
    }

    /**
     * Check if the command requires company context.
     */
    protected function requiresCompany(): bool
    {
        return true; // Most invoice commands require company context
    }

    /**
     * Validate user permissions for this command.
     */
    protected function validatePermissions(): bool
    {
        if (! $this->company) {
            return true; // No company to validate against
        }

        // Check if user has required permissions for invoice operations
        $userRole = $this->user->companies()->where('companies.id', $this->company->id)->first()?->pivot->role;

        if (! $userRole) {
            $this->error('You do not have access to this company.');

            return false;
        }

        // Define which roles can perform which actions
        $rolePermissions = [
            'owner' => ['create', 'update', 'send', 'post', 'cancel', 'list', 'show', 'duplicate', 'pdf'],
            'admin' => ['create', 'update', 'send', 'post', 'cancel', 'list', 'show', 'duplicate', 'pdf'],
            'accountant' => ['create', 'update', 'send', 'post', 'list', 'show', 'duplicate', 'pdf'],
            'viewer' => ['list', 'show', 'pdf'],
        ];

        $commandAction = $this->getCommandAction();

        if (! isset($rolePermissions[$userRole]) || ! in_array($commandAction, $rolePermissions[$userRole])) {
            $this->error("Your role ({$userRole}) does not allow this action.");

            return false;
        }

        return true;
    }

    /**
     * Get the action this command performs for permission validation.
     */
    protected function getCommandAction(): string
    {
        // Extract action from command signature
        if (preg_match('/invoice:(\w+)/', $this->signature, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }

    /**
     * Parse command options and arguments, including natural language input.
     */
    protected function parseInput(): array
    {
        $input = [];

        // Get standard options and arguments
        foreach ($this->arguments() as $key => $value) {
            if ($key !== 'command') {
                $input[$key] = $value;
            }
        }

        foreach ($this->options() as $key => $value) {
            if ($value !== null) {
                $input[$key] = $value;
            }
        }

        // Parse natural language input if provided
        if ($this->option('natural') || $this->argument('natural_input')) {
            $naturalInput = $this->option('natural') ?? $this->argument('natural_input');
            if ($naturalInput) {
                $parsed = $this->parseNaturalLanguageInput($naturalInput);

                // Merge parsed natural language with standard input
                $input = array_merge($input, $parsed);

                // Confirm interpretation if not in quiet mode
                if (! $this->option('quiet') && ! $this->option('no-interactive')) {
                    if (! $this->confirmInterpretation($parsed)) {
                        $this->error('Natural language interpretation cancelled by user.');
                        exit(1);
                    }
                }
            }
        }

        return $input;
    }

    /**
     * Get the output format for the command.
     */
    protected function getOutputFormat(): string
    {
        return $this->option('format') ?? $this->defaultFormat;
    }

    /**
     * Display a success message and optionally format output data.
     */
    protected function displaySuccess(string $message, array $data = []): void
    {
        $this->success($message, $data);

        if (! empty($data)) {
            $this->formatOutput($data, $this->getOutputFormat());
        }
    }

    /**
     * Get common command options shared across invoice commands.
     */
    protected function getCommonOptions(): array
    {
        return [
            'company' => ['company', 'c', null, 'The company ID to operate on'],
            'format' => ['format', 'f', 'table', 'Output format (table, json, csv, text)'],
            'natural' => ['natural', 'N', null, 'Natural language input for the command'],
            'quiet' => ['quiet', 'q', null, 'Suppress output and messages'],
            'no-interactive' => ['no-interactive', null, null, 'Disable interactive prompts'],
        ];
    }

    /**
     * Apply common options to the command signature.
     */
    protected function addCommonOptions(): void
    {
        $options = $this->getCommonOptions();
        $signatureParts = [];

        foreach ($options as $name => $option) {
            if (count($option) === 3) {
                $signatureParts[] = "--{$option[0]}|-{$option[1]} : {$option[2]}";
            } else {
                $signatureParts[] = "--{$option[0]}|-{$option[1]} : {$option[2]} : {$option[3]}";
            }
        }

        if (! empty($signatureParts)) {
            $this->signature .= ' {'.implode('} {', $signatureParts).'}';
        }
    }

    /**
     * Log command execution for audit purposes.
     */
    protected function logExecution(string $action, array $context = []): void
    {
        $logData = [
            'user_id' => $this->user->id,
            'company_id' => $this->company?->id,
            'command' => $this->name,
            'action' => $action,
            'context' => $context,
            'timestamp' => now(),
        ];

        \Log::info('CLI Command Executed', $logData);
    }

    /**
     * Display command help and examples.
     */
    protected function showHelp(): void
    {
        $this->info($this->description);
        $this->line('');

        $this->info('Usage:');
        $this->line('  php artisan '.$this->signature);
        $this->line('');

        if ($examples = $this->getExamples()) {
            $this->info('Examples:');
            foreach ($examples as $example) {
                $this->line("  php artisan {$example}");
            }
            $this->line('');
        }

        if ($this->requiresCompany()) {
            $this->info('Natural Language Examples:');
            $this->line('  --natural="create invoice for ACME Corp for $1500 due in 30 days"');
            $this->line('  --natural="send invoice #123 to john@example.com"');
            $this->line('  --natural="list unpaid invoices for company XYZ"');
            $this->line('');
        }
    }

    /**
     * Get usage examples for this command.
     */
    protected function getExamples(): array
    {
        return [];
    }
}
