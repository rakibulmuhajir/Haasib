<?php

namespace App\Modules\Accounting\Actions\Account;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Account;
use App\Support\PaletteFormatter;
use Illuminate\Support\Str;

class IndexAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'search' => 'nullable|string|max:255',
            'type' => 'nullable|string',
            'subtype' => 'nullable|string',
            'inactive' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $limit = $params['limit'] ?? 50;

        $query = Account::where('company_id', $company->id)
            ->orderBy('type')
            ->orderBy('code');

        $inactive = $params['inactive'] ?? false;
        if (!$inactive) {
            $query->where('is_active', true);
        }

        if (!empty($params['type'])) {
            $query->where('type', $params['type']);
        }

        if (!empty($params['subtype'])) {
            $query->where('subtype', $params['subtype']);
        }

        if (!empty($params['search'])) {
            $term = $params['search'];
            $query->where(function ($q) use ($term) {
                $q->where('code', 'ilike', "%{$term}%")
                    ->orWhere('name', 'ilike', "%{$term}%")
                    ->orWhere('description', 'ilike', "%{$term}%");
            });
        }

        $accounts = $query->limit($limit)->get();

        return [
            'data' => PaletteFormatter::table(
                headers: ['Code', 'Name', 'Type', 'Subtype', 'Currency', 'Status'],
                rows: $accounts->map(fn (Account $a) => [
                    $a->code,
                    Str::limit($a->name, 32),
                    $a->type,
                    $a->subtype,
                    $a->currency ?? $company->base_currency,
                    $a->is_active ? '{success}Active{/}' : '{secondary}Inactive{/}',
                ])->toArray(),
                footer: $accounts->count() . ' accounts',
                rowIds: $accounts->pluck('id')->toArray()
            ),
        ];
    }
}
