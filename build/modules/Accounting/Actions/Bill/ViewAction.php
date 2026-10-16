<?php

namespace App\Modules\Accounting\Actions\Bill;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Bill;
use App\Support\PaletteFormatter;

class ViewAction implements PaletteAction, \App\Contracts\DescribesPalette
{
    public function rules(): array
    {
        return ['id' => 'required|string'];
    }

    public function permission(): ?string
    {
        return null;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $bill = Bill::with('lineItems', 'vendor')
            ->where('company_id', $company->id)
            ->findOrFail($params['id']);

        return [
            'data' => [
                'bill' => $bill,
                'line_items' => $bill->lineItems,
            ],
        ];
    }

    public function palette(): array
    {
        return [
            'description' => 'View a bill',
            'fields' => [
                'id' => ['inputType' => 'search', 'searchEntity' => 'bill', 'hint' => 'Bill'],
            ],
        ];
    }
}
