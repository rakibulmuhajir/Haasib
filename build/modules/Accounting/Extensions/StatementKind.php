<?php

namespace App\Modules\Accounting\Extensions;

use App\Models\Company;
use Illuminate\Support\Collection;

/**
 * An extra kind of statement on the Statements page, contributed by a module
 * (e.g. FuelStation's amanat holders). Registered with StatementKinds.
 */
interface StatementKind
{
    /** Query-string / tab key, e.g. 'amanat'. */
    public function key(): string;

    /** Tab label. */
    public function label(): string;

    /** Label for the party picker, e.g. 'Holder'. */
    public function partyLabel(): string;

    /** Label for the "everyone" choice, e.g. 'All holders'. */
    public function allLabel(): string;

    public function available(Company $company): bool;

    /** Parties to list; each has id + name (+ customer_number when relevant). */
    public function options(Company $company): Collection;

    /** Customer ids to drop from the Customer kind's list (holders who aren't credit customers). */
    public function hiddenCustomerIds(Company $company): array;

    /**
     * @return array{0: array, 1: array, 2: ?string} [statement, columns, resolvedId]
     */
    public function statement(Collection $options, ?string $id, string $from, string $to, bool $includeTrail): array;
}
