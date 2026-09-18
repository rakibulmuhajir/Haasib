<?php

namespace App\Support\Database;

use RuntimeException;

/**
 * Raised by TenantContextGuard when a query touches a company-scoped table
 * while the PostgreSQL session carries no app.current_company_id.
 *
 * Under enforced row level security such a query is not an error in
 * PostgreSQL's eyes: a SELECT simply matches nothing and reports success. That
 * is the dangerous half of enforcement -- a blank screen rather than a stack
 * trace. This exception turns it into a stack trace, in development and test
 * only.
 */
class MissingCompanyContextException extends RuntimeException
{
    public static function forQuery(string $table, string $sql, string $connection): self
    {
        return new self(
            "No app.current_company_id is set, but a query touched the company-scoped table [{$table}] "
            ."on connection [{$connection}]. Under enforced row level security this query reads nothing "
            ."and reports success, or is refused outright on write.\n"
            ."Set context with CompanyContext::setContext(\$company), or wrap genuinely cross-company work in "
            ."CompanyContext::crossCompany(fn () => ...).\n"
            ."Query: {$sql}"
        );
    }
}
