<?php

namespace Modules\Ledger\Domain\PeriodClose\Actions;

use App\Models\AccountingPeriod;
use App\Models\User;
use Modules\Ledger\Services\PeriodCloseService;

class ReopenPeriodCloseAction
{
    public function __construct(
        private PeriodCloseService $periodCloseService
    ) {}

    /**
     * Execute the period reopen action.
     */
    public function execute(AccountingPeriod $period, array $reopenData, User $user): bool
    {
        // Validate inputs
        $this->validateInputs($period, $reopenData, $user);

        // Prepare reopen data with company context
        $preparedData = $this->prepareReopenData($reopenData, $period);

        // Execute the reopen
        return $this->periodCloseService->reopenPeriod(
            $period->id,
            $preparedData,
            $user
        );
    }

    /**
     * Validate the inputs for period reopen.
     */
    private function validateInputs(AccountingPeriod $period, array $reopenData, User $user): void
    {
        // Validate period exists and has period close
        if (! $period->periodClose) {
            throw new \InvalidArgumentException('Period close not found for this period');
        }

        // Validate user permissions
        if (! $user->can('period-close.reopen')) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have permission to reopen periods');
        }

        // Validate period is closed
        if ($period->status !== 'closed') {
            throw new \InvalidArgumentException('Only closed periods can be reopened');
        }

        if ($period->periodClose->status !== 'closed') {
            throw new \InvalidArgumentException('Period close is not in closed status');
        }

        // Validate required fields
        $this->validateRequiredFields($reopenData);

        // Validate business rules
        $this->validateBusinessRules($period, $reopenData, $user);

        // Validate company scoping
        $this->validateCompanyScoping($period, $user);
    }

    /**
     * Validate required fields.
     */
    private function validateRequiredFields(array $data): void
    {
        $requiredFields = ['reason', 'reopen_until'];

        foreach ($requiredFields as $field) {
            if (! isset($data[$field]) || empty(trim($data[$field]))) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }
    }

    /**
     * Validate business rules for reopening.
     */
    private function validateBusinessRules(AccountingPeriod $period, array $reopenData, User $user): void
    {
        // Validate reopen_until date
        try {
            $reopenUntil = \Carbon\Carbon::parse($reopenData['reopen_until']);

            if ($reopenUntil->isPast()) {
                throw new \InvalidArgumentException('Reopen until date must be in the future');
            }

            // Limit maximum reopen window based on user role
            $maxDays = $this->getMaxReopenDays($user, $period);
            if ($reopenUntil->diffInDays(now()) > $maxDays) {
                throw new \InvalidArgumentException("Reopen window cannot exceed {$maxDays} days for users with role '{$this->getUserRole($user, $period->company_id)}'");
            }

        } catch (\Carbon\Exceptions\InvalidFormatException $e) {
            throw new \InvalidArgumentException('Invalid date format for reopen_until');
        }

        // Validate reason content
        if (strlen($reopenData['reason']) > 500) {
            throw new \InvalidArgumentException('Reopen reason cannot exceed 500 characters');
        }

        // Check for suspicious patterns in reason
        if ($this->containsSuspiciousContent($reopenData['reason'])) {
            throw new \InvalidArgumentException('Reopen reason contains suspicious content');
        }

        // Check if period was closed too recently
        if ($this->wasClosedRecently($period)) {
            throw new \InvalidArgumentException('Period was closed too recently to be reopened. Please wait at least 24 hours.');
        }

        // Check reopen frequency limits
        $this->validateReopenFrequency($period);
    }

    /**
     * Validate company scoping.
     */
    private function validateCompanyScoping(AccountingPeriod $period, User $user): void
    {
        // Check if user belongs to the period's company
        if (! $user->companies()->where('company_id', $period->company_id)->exists()) {
            throw new \Illuminate\Auth\Access\AuthorizationException('User does not have access to this company');
        }

        // Additional scoping validation can be added here
        // For example: checking specific permissions per company
    }

    /**
     * Prepare reopen data with additional context.
     */
    private function prepareReopenData(array $reopenData, AccountingPeriod $period): array
    {
        $prepared = $reopenData;

        // Add company context
        $prepared['company_id'] = $period->company_id;

        // Add timestamp
        $prepared['reopen_requested_at'] = now()->toISOString();

        // Add user context
        $prepared['requested_by_user_id'] = auth()->id();

        // Add IP address for audit
        $prepared['request_ip'] = request()->ip();

        // Add user agent for audit
        $prepared['user_agent'] = request()->userAgent();

        // Add session ID for audit
        $prepared['session_id'] = session()->getId();

        return $prepared;
    }

    /**
     * Get maximum allowed reopen days based on user role.
     */
    private function getMaxReopenDays(User $user, AccountingPeriod $period): int
    {
        $role = $this->getUserRole($user, $period->company_id);

        $roleLimits = [
            'cfo' => 90,        // CFO can reopen for up to 90 days
            'controller' => 30, // Controller can reopen for up to 30 days
            'accountant' => 7,  // Accountant can reopen for up to 7 days
        ];

        return $roleLimits[$role] ?? 7; // Default to 7 days for unknown roles
    }

    /**
     * Get user role in company context.
     */
    private function getUserRole(User $user, string $companyId): string
    {
        $membership = $user->companies()->where('company_id', $companyId)->first();

        return $membership?->pivot->role ?? 'unknown';
    }

    /**
     * Check if content contains suspicious patterns.
     */
    private function containsSuspiciousContent(string $content): bool
    {
        $suspiciousPatterns = [
            '/test/i',
            '/dummy/i',
            '/fake/i',
            '/sample/i',
            '/debug/i',
        ];

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if period was closed too recently.
     */
    private function wasClosedRecently(AccountingPeriod $period): bool
    {
        if (! $period->periodClose || ! $period->periodClose->closed_at) {
            return false;
        }

        return $period->periodClose->closed_at->diffInHours(now()) < 24;
    }

    /**
     * Validate reopen frequency limits.
     */
    private function validateReopenFrequency(AccountingPeriod $period): void
    {
        if (! $period->periodClose) {
            return;
        }

        $metadata = $period->periodClose->metadata ?? [];
        $reopenMetadata = $metadata['reopen_metadata'] ?? [];
        $reopenedTimes = $reopenMetadata['reopened_times'] ?? 0;

        // Define limits based on time period
        $limits = [
            30 => 3,  // Max 3 reopens in 30 days
            90 => 5,  // Max 5 reopens in 90 days
            365 => 10, // Max 10 reopens in 1 year
        ];

        foreach ($limits as $days => $maxReopens) {
            if ($this->countRecentReopens($period, $days) >= $maxReopens) {
                throw new \InvalidArgumentException("Period has been reopened too many times in the last {$days} days. Maximum allowed: {$maxReopens}");
            }
        }

        // Check total lifetime limit
        if ($reopenedTimes >= 10) {
            throw new \InvalidArgumentException('Period has reached the maximum lifetime limit of 10 reopens');
        }
    }

    /**
     * Count recent reopens within specified days.
     */
    private function countRecentReopens(AccountingPeriod $period, int $days): int
    {
        if (! $period->periodClose) {
            return 0;
        }

        $auditTrail = $period->periodClose->audit_trail ?? [];
        $reopenEvents = $auditTrail['reopen_events'] ?? [];

        $cutoffDate = now()->subDays($days);

        return collect($reopenEvents)->filter(function ($event) use ($cutoffDate) {
            return isset($event['timestamp']) &&
                   \Carbon\Carbon::parse($event['timestamp'])->isAfter($cutoffDate);
        })->count();
    }

    /**
     * Get reopen requirements and warnings for the UI.
     */
    public function getReopenRequirements(AccountingPeriod $period, User $user): array
    {
        $requirements = [];
        $warnings = [];

        // Check user role and permissions
        if (! $user->can('period-close.reopen')) {
            $warnings[] = 'You do not have permission to reopen periods. Please contact your administrator.';
        }

        // Check user role limits
        $maxDays = $this->getMaxReopenDays($user, $period);
        $requirements[] = "Your role allows reopening periods for up to {$maxDays} days.";

        // Check period age
        if ($period->periodClose && $period->periodClose->closed_at) {
            $daysSinceClose = $period->periodClose->closed_at->diffInDays(now());
            if ($daysSinceClose > 365) {
                $warnings[] = 'This period was closed more than 1 year ago. Reopening may require special approval.';
            }
        }

        // Check reopen frequency
        $reopenedTimes = $period->periodClose->metadata['reopen_metadata']['reopened_times'] ?? 0;
        if ($reopenedTimes >= 3) {
            $warnings[] = "This period has been reopened {$reopenedTimes} times. Consider the impact on financial reporting.";
        }

        // Check if recently closed
        if ($this->wasClosedRecently($period)) {
            $requirements[] = 'Period must be closed for at least 24 hours before reopening.';
        }

        return [
            'requirements' => $requirements,
            'warnings' => $warnings,
            'can_reopen' => empty($warnings) && $user->can('period-close.reopen'),
            'user_role' => $this->getUserRole($user, $period->company_id),
            'max_reopen_days' => $maxDays,
        ];
    }

    /**
     * Get recommended reopen duration based on reason and context.
     */
    public function getRecommendedReopenDuration(AccountingPeriod $period, string $reason, User $user): int
    {
        $userRole = $this->getUserRole($user, $period->company_id);
        $maxDays = $this->getMaxReopenDays($user, $period);

        // Base recommendations by reason type
        $reasonKeywords = [
            'audit' => 14,
            'adjustment' => 7,
            'correction' => 10,
            'restatement' => 30,
            'error' => 5,
            'clarification' => 3,
        ];

        foreach ($reasonKeywords as $keyword => $recommendedDays) {
            if (stripos($reason, $keyword) !== false) {
                return min($recommendedDays, $maxDays);
            }
        }

        // Default recommendation based on role
        $roleDefaults = [
            'cfo' => 14,
            'controller' => 10,
            'accountant' => 5,
        ];

        return $roleDefaults[$userRole] ?? 7;
    }

    /**
     * Validate the reopen reason and suggest improvements.
     */
    public function validateReason(string $reason): array
    {
        $issues = [];
        $suggestions = [];

        // Check length
        if (strlen($reason) < 10) {
            $issues[] = 'Reason is too short. Please provide more detail.';
        }

        if (strlen($reason) > 500) {
            $issues[] = 'Reason is too long. Please keep it under 500 characters.';
        }

        // Check for clarity
        if (! preg_match('/[A-Z]/', $reason)) {
            $suggestions[] = 'Consider starting the reason with a capital letter for better readability.';
        }

        // Check for specific details
        if (! preg_match('/(audit|adjustment|correction|error|clarification)/i', $reason)) {
            $suggestions[] = 'Include specific details about why the period needs to be reopened (e.g., "audit adjustment", "correction of error").';
        }

        // Check for vague terms
        $vagueTerms = ['stuff', 'things', 'misc', 'etc'];
        foreach ($vagueTerms as $term) {
            if (stripos($reason, $term) !== false) {
                $suggestions[] = 'Replace vague terms like "'.$term.'" with specific details.';
            }
        }

        return [
            'is_valid' => empty($issues),
            'issues' => $issues,
            'suggestions' => $suggestions,
            'character_count' => strlen($reason),
        ];
    }
}
