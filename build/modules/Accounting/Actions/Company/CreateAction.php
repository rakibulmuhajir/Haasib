<?php

namespace App\Modules\Accounting\Actions\Company;

use App\Constants\Permissions;
use App\Constants\Tables;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Company;
use App\Models\CompanyCurrency;
use App\Services\CompanyRbacBootstrapper;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'currency' => 'required|string|size:3',
            'industry' => 'nullable|string|max:255',
            'country' => 'nullable|string|size:2',
            'language' => 'nullable|string|size:2',
            'locale' => 'nullable|string|max:10',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::COMPANY_CREATE;
    }

    public function handle(array $params): array
    {
        $params['currency'] = strtoupper($params['currency']);
        $slug = $this->uniqueSlug(Str::slug($params['name']));

        return DB::transaction(function () use ($params, $slug) {
            $company = Company::create([
                'name' => $params['name'],
                'industry' => $params['industry'] ?? null,
                'slug' => $slug,
                'country' => $params['country'] ?? null,
                'base_currency' => $params['currency'],
                'language' => $params['language'] ?? 'en',
                'locale' => $params['locale'] ?? 'en_US',
                'created_by_user_id' => Auth::id(),
                'is_active' => true,
            ]);

            CompanyCurrency::create([
                'company_id' => $company->id,
                'currency_code' => $params['currency'],
                'is_base' => true,
                'enabled_at' => now(),
            ]);

            DB::table(Tables::COMPANY_USER)->insert([
                'company_id' => $company->id,
                'user_id' => Auth::id(),
                'role' => 'owner',
                'invited_by_user_id' => Auth::id(),
                'joined_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            app(CompanyRbacBootstrapper::class)->bootstrap($company, Auth::user());

            // Set as active context
            CompanyContext::setContext($company);

            return [
                'message' => "Company created: {$company->name} ({$company->slug})",
                'data' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'slug' => $company->slug,
                    'currency' => $company->base_currency,
                ],
                'redirect' => "/{$company->slug}/dashboard",
            ];
        });
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base;
        $counter = 1;

        while (Company::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
