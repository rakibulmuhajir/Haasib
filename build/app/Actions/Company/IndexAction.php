<?php

namespace App\Actions\Company;

use App\Contracts\PaletteAction;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class IndexAction implements PaletteAction
{
    public function rules(): array
    {
        return [];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function handle(array $params): array
    {
        $companies = DB::table('auth.companies as c')
            ->join('auth.company_user as cu', 'c.id', '=', 'cu.company_id')
            ->where('cu.user_id', Auth::id())
            ->where('cu.is_active', true)
            ->select('c.name', 'c.slug', 'c.base_currency', 'cu.role', 'c.is_active')
            ->orderBy('c.name')
            ->get();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Name', 'Slug', 'Currency', 'Role', 'Status'],
                rows: $companies->map(fn($c) => [
                    $c->name,
                    $c->slug,
                    $c->base_currency,
                    ucfirst($c->role),
                    $c->is_active ? '{success}● Active{/}' : '{secondary}○ Inactive{/}',
                ])->toArray(),
                footer: $companies->count() . ' companies'
            ),
        ];
    }
}
