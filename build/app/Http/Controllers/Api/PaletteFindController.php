<?php

namespace App\Http\Controllers\Api;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Support\Palette\PaletteSearchSources;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bare text typed in the palette: the customers, vendors, invoices and bills it names, five of each. */
class PaletteFindController extends Controller
{
    private const PER_GROUP = 5;

    /** group => [search entity, permission to see it, page path under /{company}] */
    private const GROUPS = [
        'Customers' => ['customer', Permissions::CUSTOMER_VIEW, 'customers'],
        'Vendors' => ['vendor', Permissions::VENDOR_VIEW, 'vendors'],
        'Invoices' => ['invoice', Permissions::INVOICE_VIEW, 'invoices'],
        'Bills' => ['bill', Permissions::BILL_VIEW, 'bills'],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $company = CompanyContext::getCompany();
        $q = trim((string) $request->query('q', ''));
        if ($company === null || $q === '') {
            return response()->json(['results' => []]);
        }

        $results = [];
        foreach (self::GROUPS as $group => [$entity, $permission, $path]) {
            if (! $request->user()->hasCompanyPermission($permission)) {
                continue;
            }

            foreach (array_slice(PaletteSearchSources::search($entity, $company->id, $q), 0, self::PER_GROUP) as $hit) {
                $row = ['group' => $group, 'value' => $hit['value'], 'label' => $hit['label']];
                if (! empty($hit['meta'])) {
                    $row['meta'] = $hit['meta'];
                }
                $row['url'] = "/{$company->slug}/{$path}/{$hit['value']}";
                $results[] = $row;
            }
        }

        return response()->json(['results' => $results]);
    }
}
