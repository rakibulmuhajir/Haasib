<?php

namespace Modules\Ledger\Domain\PeriodClose\Exceptions;

use Exception;

class PeriodCloseException extends Exception
{
    /**
     * Create a new period close exception.
     */
    public static function cannotStartPeriod(string $reason): self
    {
        return new self("Cannot start period close: {$reason}");
    }

    /**
     * Create a new exception for invalid period status.
     */
    public static function invalidPeriodStatus(string $status): self
    {
        return new self("Cannot close period with status: {$status}");
    }

    /**
     * Create a new exception for already existing period close.
     */
    public static function alreadyInProgress(string $periodId): self
    {
        return new self("Period close is already in progress for period: {$periodId}");
    }

    /**
     * Create a new exception for validation failures.
     */
    public static function validationFailed(array $errors): self
    {
        $message = 'Period close validation failed: '.implode(', ', $errors);

        return new self($message);
    }

    /**
     * Create a new exception for template not found.
     */
    public static function templateNotFound(string $templateId): self
    {
        return new self("Period close template not found: {$templateId}");
    }

    /**
     * Create a new exception for task not found.
     */
    public static function taskNotFound(string $taskId): self
    {
        return new self("Period close task not found: {$taskId}");
    }

    /**
     * Create a new exception for task cannot be completed.
     */
    public static function taskCannotBeCompleted(string $taskId, string $reason): self
    {
        return new self("Task {$taskId} cannot be completed: {$reason}");
    }

    /**
     * Create a new exception for unauthorized action.
     */
    public static function unauthorized(string $action): self
    {
        return new self("Unauthorized to perform action: {$action}");
    }
}
