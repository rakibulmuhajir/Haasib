<?php

namespace App\Actions\User;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Support\PaletteFormatter;
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
        $company = CompanyContext::requireCompany();

        $users = DB::table('auth.users as u')
            ->join('auth.company_user as cu', 'u.id', '=', 'cu.user_id')
            ->where('cu.company_id', $company->id)
            ->select('u.email', 'u.name', 'cu.role', 'cu.is_active', 'cu.joined_at')
            ->orderBy('u.name')
            ->get();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Email', 'Name', 'Role', 'Status', 'Joined'],
                rows: $users->map(fn($u) => [
                    $u->email,
                    $u->name,
                    ucfirst($u->role),
                    $u->is_active ? '{success}● Active{/}' : '{secondary}○ Inactive{/}',
                    $u->joined_at ? date('M d, Y', strtotime($u->joined_at)) : '-',
                ])->toArray(),
                footer: $users->count() . ' users'
            ),
        ];
    }
}
