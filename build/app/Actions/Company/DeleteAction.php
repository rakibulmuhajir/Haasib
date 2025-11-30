<?php

namespace App\Actions\Company;

use App\Constants\Permissions;
use App\Contracts\PaletteAction;
use App\Models\Company;
use App\Services\CurrentCompany;
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
        return Permissions::COMPANY_DELETE;
    }

    public function handle(array $params): array
    {
        $company = Company::where('slug', $params['slug'])->firstOrFail();

        return DB::transaction(function () use ($company) {
            DB::table('auth.company_user')
                ->where('company_id', $company->id)
                ->update(['is_active' => false, 'updated_at' => now()]);

            $company->update(['is_active' => false]);

            $currentCompany = app(CurrentCompany::class)->get();
            if ($currentCompany && $currentCompany->id === $company->id) {
                app(CurrentCompany::class)->clear();
            }

            return [
                'message' => "Company deleted: {$company->name}",
                'data' => ['id' => $company->id],
                'redirect' => '/dashboard',
            ];
        });
    }
}
