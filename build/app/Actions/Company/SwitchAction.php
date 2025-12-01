<?php

namespace App\Actions\Company;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SwitchAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'slug' => 'required|string',
        ];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function handle(array $params): array
    {
        $company = Company::where('slug', $params['slug'])->firstOrFail();

        $membership = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->first();

        if (!$membership) {
            throw new \Exception("You are not a member of {$company->name}");
        }

        CompanyContext::setContext($company);

        $userCount = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->count();

        return [
            'message' => "Switched to {$company->name}",
            'data' => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
                'user_count' => $userCount,
                'status' => $company->is_active ? 'active' : 'inactive',
                'summary' => [
                    'invoices_due' => 0,
                    'open_bills' => 0,
                ],
            ],
            'redirect' => "/{$company->slug}/dashboard",
        ];
    }
}
