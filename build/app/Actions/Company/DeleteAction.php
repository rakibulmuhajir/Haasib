<?php

namespace App\Actions\Company;

use App\Constants\Permissions;
use App\Constants\Tables;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Company;
use Illuminate\Support\Facades\DB;

class DeleteAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'slug' => 'required|string',
        ];
    }

    public function permission(): ?string
    {
        // Permission check handled manually in handle() for the specific company being deleted
        return null;
    }

    public function handle(array $params): array
    {
        $company = Company::where('slug', $params['slug'])->firstOrFail();

        // Check permission in the context of the company being deleted
        $hasPermission = CompanyContext::withContext($company, function () use ($company) {
            return \Illuminate\Support\Facades\Auth::user()->hasCompanyPermission(Permissions::COMPANY_DELETE);
        });

        if (!$hasPermission) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Permission denied: ' . Permissions::COMPANY_DELETE);
        }

        return DB::transaction(function () use ($company) {
            DB::table(Tables::COMPANY_USER)
                ->where('company_id', $company->id)
                ->update(['is_active' => false, 'updated_at' => now()]);

            $company->update(['is_active' => false]);

            $currentCompany = CompanyContext::getCompany();
            if ($currentCompany && $currentCompany->id === $company->id) {
                CompanyContext::clearContext();
            }

            return [
                'message' => "Company deleted: {$company->name}",
                'data' => [
                    'id' => $company->id,
                    'slug' => $company->slug,
                ],
                'redirect' => '/dashboard',
            ];
        });
    }
}
