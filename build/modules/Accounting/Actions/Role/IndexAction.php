<?php

namespace App\Modules\Accounting\Actions\Role;

use App\Contracts\PaletteAction;
use App\Models\Role;
use App\Support\PaletteFormatter;
use App\Facades\CompanyContext;

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
        
        $company = CompanyContext::requireCompany();

        $roles = Role::with('permissions')
            ->when($company, fn($query) => $query->where(fn($inner) => $inner
                ->where('company_id', $company->id)
                ->orWhereNull('company_id')
            ))
            ->orderBy('name')
            ->get();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Role', 'Permissions', 'Users'],
                rows: $roles->map(fn($r) => [
                    $r->name,
                    $r->permissions->pluck('name')->implode(', ') ?: '{secondary}None{/}',
                    $r->users()
                        ->when($company, fn($users) => $users->wherePivot('company_id', $company->id))
                        ->count(),
                ])->toArray(),
                footer: $roles->count() . ' roles'
            ),
        ];
    }
}
