<?php

namespace App\Support\Palette\Commands;

use App\Facades\CompanyContext;
use App\Http\Controllers\Api\PaletteDefaultsController;
use App\Models\Company;
use App\Models\User;

/** Who is typing, for which company, and the company's quick-entry defaults. */
final class ResolveContext
{
    /** @param array<string, mixed> $defaults */
    public function __construct(
        public Company $company,
        public User $user,
        public string $today,
        public array $defaults,
    ) {}

    /** The current company and signed-in user. */
    public static function make(): self
    {
        $company = CompanyContext::requireCompany();
        $defaults = PaletteDefaultsController::defaultsFor($company);

        return new self($company, auth()->user(), $defaults['today'], $defaults);
    }
}
