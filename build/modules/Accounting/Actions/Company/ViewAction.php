<?php

namespace App\Modules\Accounting\Actions\Company;

use App\Contracts\PaletteAction;
use App\Constants\Tables;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ViewAction implements PaletteAction
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
        $company = DB::table(Tables::COMPANIES.' as c')
            ->join(Tables::COMPANY_USER.' as cu', 'c.id', '=', 'cu.company_id')
            ->where('c.slug', $params['slug'])
            ->where('cu.user_id', Auth::id())
            ->where('cu.is_active', true)
            ->select(
                'c.id',
                'c.name',
                'c.slug',
                'c.base_currency',
                'c.country',
                'c.language',
                'c.locale',
                'c.is_active',
                'cu.role',
                'c.created_at'
            )
            ->first();

        if (!$company) {
            throw new \Exception('Company not found or you do not have access.');
        }

        $userCount = DB::table(Tables::COMPANY_USER)
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->count();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Field', 'Value'],
                rows: [
                    ['Name', $company->name],
                    ['Slug', $company->slug],
                    ['Status', $company->is_active ? '{success}Active{/}' : '{secondary}Inactive{/}'],
                    ['Role', ucfirst($company->role)],
                    ['Base Currency', $company->base_currency],
                    ['Country', $company->country ?? '—'],
                    ['Language', $company->language ?? '—'],
                    ['Locale', $company->locale ?? '—'],
                    ['Users', (string) $userCount],
                    ['Created', optional($company->created_at)->toDateTimeString() ?? '—'],
                ],
                footer: 'Company details'
            ),
        ];
    }
}
