<?php

namespace Modules\Accounting\Config;

class SecurityConfig
{
    /**
     * Maximum allowed amounts for accounting operations.
     */
    public const MAX_SINGLE_ENTRY_AMOUNT = 999999999.99;

    public const MAX_BATCH_TOTAL_AMOUNT = 9999999999.99;

    /**
     * Rate limiting configuration.
     */
    public const RATE_LIMITS = [
        'api_create' => ['attempts' => 10, 'minutes' => 1],
        'api_update' => ['attempts' => 30, 'minutes' => 1],
        'api_read' => ['attempts' => 100, 'minutes' => 1],
        'batch_operations' => ['attempts' => 5, 'minutes' => 1],
    ];

    /**
     * Input validation constraints.
     */
    public const VALIDATION_RULES = [
        'max_description_length' => 1000,
        'max_name_length' => 255,
        'max_reference_length' => 100,
        'max_search_length' => 100,
        'max_per_page' => 100,
        'max_entries_per_batch' => 1000,
        'max_lines_per_entry' => 100,
    ];

    /**
     * Sensitive operations requiring additional verification.
     */
    public const SENSITIVE_OPERATIONS = [
        'batch_posting',
        'batch_deletion',
        'entry_voiding',
        'template_deletion',
        'mass_changes',
    ];

    /**
     * Fields that should be sanitized before processing.
     */
    public const SANITIZED_FIELDS = [
        'description',
        'name',
        'reference',
        'search',
        'notes',
    ];

    /**
     * Company-level permissions required for operations.
     */
    public const COMPANY_PERMISSIONS = [
        'view_entries' => 'accounting.entries.view',
        'create_entries' => 'accounting.entries.create',
        'approve_entries' => 'accounting.entries.approve',
        'post_entries' => 'accounting.entries.post',
        'manage_batches' => 'accounting.batches.manage',
        'view_reports' => 'accounting.reports.view',
        'manage_templates' => 'accounting.templates.manage',
    ];

    /**
     * Audit log configuration.
     */
    public const AUDIT_CONFIG = [
        'log_all_changes' => true,
        'log_sensitive_data' => false,
        'retention_days' => 2555, // 7 years
        'sensitive_fields' => [
            'password',
            'token',
            'secret',
            'key',
        ],
    ];

    /**
     * Allowed file upload types and sizes.
     */
    public const UPLOAD_CONFIG = [
        'max_file_size' => 10 * 1024 * 1024, // 10MB
        'allowed_extensions' => ['csv', 'xlsx', 'pdf', 'doc', 'docx'],
        'allowed_mime_types' => [
            'text/csv',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ];

    /**
     * Get rate limit configuration for an operation type.
     */
    public static function getRateLimit(string $operation): array
    {
        return self::RATE_LIMITS[$operation] ?? ['attempts' => 60, 'minutes' => 1];
    }

    /**
     * Check if an operation is considered sensitive.
     */
    public static function isSensitiveOperation(string $operation): bool
    {
        return in_array($operation, self::SENSITIVE_OPERATIONS);
    }

    /**
     * Get validation rules as Laravel rules array.
     */
    public static function getValidationRules(): array
    {
        return [
            'description' => 'nullable|string|max:'.self::VALIDATION_RULES['max_description_length'],
            'name' => 'required|string|max:'.self::VALIDATION_RULES['max_name_length'],
            'reference' => 'nullable|string|max:'.self::VALIDATION_RULES['max_reference_length'],
            'search' => 'nullable|string|max:'.self::VALIDATION_RULES['max_search_length'],
            'per_page' => 'nullable|integer|min:1|max:'.self::VALIDATION_RULES['max_per_page'],
            'amount' => 'required|numeric|min:0|max:'.self::MAX_SINGLE_ENTRY_AMOUNT,
        ];
    }

    /**
     * Sanitize input values.
     */
    public static function sanitizeInput(array $data): array
    {
        foreach (self::SANITIZED_FIELDS as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
                $data[$field] = htmlspecialchars($data[$field], ENT_QUOTES, 'UTF-8');
            }
        }

        return $data;
    }

    /**
     * Validate amount constraints.
     */
    public static function validateAmount(float $amount, string $operation = 'single'): bool
    {
        $maxAmount = $operation === 'batch'
            ? self::MAX_BATCH_TOTAL_AMOUNT
            : self::MAX_SINGLE_ENTRY_AMOUNT;

        return $amount >= 0 && $amount <= $maxAmount;
    }
}
