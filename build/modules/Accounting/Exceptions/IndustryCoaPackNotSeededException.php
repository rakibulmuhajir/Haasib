<?php

namespace App\Modules\Accounting\Exceptions;

use RuntimeException;

/**
 * Thrown when an industry's chart-of-accounts pack resolves but has zero
 * template rows in acct.industry_coa_templates.
 *
 * This is a data-seeding problem, not a "nothing to do" situation: a company
 * onboarded (or repaired) against a pack in this state would end up with no
 * Accounts Receivable, no Accounts Payable and no retained-earnings account,
 * and the first credit sale would fail at posting. The condition must never
 * be reported as a success.
 */
class IndustryCoaPackNotSeededException extends RuntimeException
{
    public static function forIndustry(string $industryCode): self
    {
        return new self(
            "The chart-of-accounts pack for industry \"{$industryCode}\" has no template accounts seeded ".
            '(acct.industry_coa_packs row exists but acct.industry_coa_templates has zero rows for it). '.
            'Refusing to report a chart of accounts as created when nothing would actually be created. '.
            'Seed the pack templates (see database/seeders/IndustryCoaPackSeeder.php) before retrying, '.
            'or run `php artisan accounting:repair-coa` once it is seeded.'
        );
    }
}
