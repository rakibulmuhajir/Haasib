<?php

namespace App\Modules\Accounting\Extensions;

use App\Models\Company;

/**
 * An extra party-shaped section of the Opening Balances page, contributed by a module
 * (e.g. FuelStation's amanat depositors). Registered with OpeningBalanceSections.
 *
 * Rows are {party()}_id + amount, posted by SaveAction in the order sections are registered.
 */
interface OpeningBalanceSection
{
    /** Request / payload / error key, e.g. 'amanat'. */
    public function key(): string;

    /** Card heading, e.g. 'Amanat depositors'. */
    public function label(): string;

    /** Card sub-heading. */
    public function description(): string;

    /** Row picker label, e.g. 'Depositor'. */
    public function partyLabel(): string;

    /** Row amount label, e.g. 'Held'. */
    public function amountLabel(): string;

    /** One short line under Quick Add's advance field. */
    public function hint(): string;

    /** 'customer' | 'vendor': rows carry {party}_id. */
    public function party(): string;

    /** 'liability' | 'asset'. */
    public function side(): string;

    /** Whether the company can enter and post rows of this section. */
    public function available(Company $company): bool;

    /** Whether Quick Add may offer it now (available and its control account exists). */
    public function ready(Company $company): bool;

    /** Validation rules for the section's payload keys. */
    public function rules(): array;

    /**
     * Append journal lines and queued side effects (closures run once the journal exists).
     *
     * @param  array<int, array>  $lines
     * @param  array<int, callable>  $pending
     */
    public function addLines(string $companyId, array $params, array &$lines, array &$pending): void;

    /**
     * Throw when re-entering would orphan used balances; return state for undo().
     *
     * @param  array<int, string>  $entryIds  journal line ids of one opening journal
     */
    public function prepareReentry(string $companyId, array $entryIds): mixed;

    public function undo(string $companyId, mixed $state): void;

    /**
     * Rows for the view: [{{party}_id, {party}_name, amount}].
     *
     * @param  array<int, string>  $entryIds
     */
    public function rows(string $companyId, array $entryIds): array;
}
