<?php

namespace App\Actions\Company;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class DeactivateCompany
{
    public function handle(array $payload, User $actor): array
    {
        // Validate payload
        $validated = Validator::make($payload, [
            'company' => 'required|string',
        ])->validate();

        // Find company (supports UUID, slug, or ID)
        $company = $this->findCompany($validated['company']);

        // Authorization check
        abort_unless($actor->isSuperAdmin(), 403, 'Only SuperAdmins can deactivate companies');

        // Check if already inactive
        if (! $company->is_active) {
            throw ValidationException::withMessages([
                'company' => 'Company is already inactive',
            ]);
        }

        // Deactivate company
        $company->deactivate();

        return [
            'id' => $company->id,
            'name' => $company->name,
            'is_active' => false,
            'deactivated_at' => $company->deactivated_at,
            'deactivated_by' => $actor->id,
        ];
    }

    private function findCompany(string $identifier): Company
    {
        // Try UUID first
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $identifier)) {
            $company = Company::find($identifier);
            if ($company) {
                return $company;
            }
        }

        // Try slug
        $company = Company::where('slug', $identifier)->first();
        if ($company) {
            return $company;
        }

        // Try ID (for backward compatibility)
        $company = Company::find($identifier);
        if ($company) {
            return $company;
        }

        throw ValidationException::withMessages([
            'company' => 'Company not found',
        ]);
    }
}
