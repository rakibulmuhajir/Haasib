<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * API Controller for fetching flag values for the command palette.
 *
 * Route: GET /api/palette/flag-values
 * Example: /api/palette/flag-values?entity=company&verb=create&flag=industry&q=tech
 */
class PaletteFlagController extends Controller
{
    public function flagValues(Request $request): JsonResponse
    {
        $entity = (string) $request->get('entity', '');
        $verb = (string) $request->get('verb', '');
        $flag = (string) $request->get('flag', '');
        $query = (string) $request->get('q', '');

        $values = $this->getFlagValues($entity, $verb, $flag, $query);

        return response()->json(['values' => $values]);
    }

    private function getFlagValues(string $entity, string $verb, string $flag, string $query): array
    {
        $flagMappings = [
            'company' => [
                'industry' => fn () => $this->getIndustries($query),
                'country' => fn () => $this->getCountries($query),
                'currency' => fn () => $this->getCurrencies($query),
            ],
            'customer' => [
                'country' => fn () => $this->getCountries($query),
                'currency' => fn () => $this->getCurrencies($query),
                'type' => fn () => $this->getCustomerTypes($query),
            ],
            'invoice' => [
                'currency' => fn () => $this->getCurrencies($query),
                'status' => fn () => $this->getInvoiceStatuses($query),
            ],
            'product' => [
                'category' => fn () => $this->getProductCategories($query),
                'unit' => fn () => $this->getProductUnits($query),
            ],
        ];

        $resolver = $flagMappings[$entity][$flag] ?? null;

        if ($resolver) {
            return $resolver();
        }

        return [];
    }

    private function getIndustries(string $query): array
    {
        $industries = [
            ['value' => 'education', 'label' => 'Education', 'icon' => '🎓'],
            ['value' => 'energy', 'label' => 'Energy', 'icon' => '⚡'],
            ['value' => 'ai', 'label' => 'AI', 'icon' => '🤖'],
            ['value' => 'technology', 'label' => 'Technology', 'icon' => '💻'],
            ['value' => 'retail', 'label' => 'Retail', 'icon' => '🛒'],
            ['value' => 'services', 'label' => 'Services', 'icon' => '🛠️'],
        ];

        return $this->filterByQuery($industries, $query);
    }

    private function getCountries(string $query): array
    {
        // If a countries table exists, prefer it
        if (DB::getSchemaBuilder()->hasTable('countries')) {
            $rows = DB::table('countries')
                ->selectRaw("code as value, name as label, '🌍' as icon")
                ->when($query !== '', function ($q) use ($query) {
                    $q->where(function ($inner) use ($query) {
                        $inner->where('code', 'ilike', $query . '%')
                            ->orWhere('name', 'ilike', '%' . $query . '%');
                    });
                })
                ->orderBy('name')
                ->limit(25)
                ->get()
                ->toArray();

            $asArray = array_map(fn($row) => (array) $row, $rows);
            return $this->filterByQuery($asArray, $query);
        }

        // Fallback static list
        $countries = [
            ['value' => 'US', 'label' => 'United States', 'icon' => '🇺🇸'],
            ['value' => 'GB', 'label' => 'United Kingdom', 'icon' => '🇬🇧'],
            ['value' => 'DE', 'label' => 'Germany', 'icon' => '🇩🇪'],
            ['value' => 'FR', 'label' => 'France', 'icon' => '🇫🇷'],
            ['value' => 'JP', 'label' => 'Japan', 'icon' => '🇯🇵'],
            ['value' => 'CN', 'label' => 'China', 'icon' => '🇨🇳'],
            ['value' => 'IN', 'label' => 'India', 'icon' => '🇮🇳'],
            ['value' => 'AU', 'label' => 'Australia', 'icon' => '🇦🇺'],
            ['value' => 'CA', 'label' => 'Canada', 'icon' => '🇨🇦'],
            ['value' => 'BR', 'label' => 'Brazil', 'icon' => '🇧🇷'],
            ['value' => 'PK', 'label' => 'Pakistan', 'icon' => '🇵🇰'],
            ['value' => 'AE', 'label' => 'United Arab Emirates', 'icon' => '🇦🇪'],
        ];

        return $this->filterByQuery($countries, $query);
    }

    private function getCurrencies(string $query): array
    {
        $rows = DB::table('currencies')
            ->select('code as value', 'name as label', DB::raw("'💱' as icon"))
            ->where('is_active', true)
            ->when($query !== '', function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('code', 'ilike', $query . '%')
                        ->orWhere('name', 'ilike', '%' . $query . '%');
                });
            })
            ->orderBy('code')
            ->limit(25)
            ->get()
            ->toArray();

        $asArray = array_map(fn($row) => (array) $row, $rows);

        return $this->filterByQuery($asArray, $query);
    }

    private function getCustomerTypes(string $query): array
    {
        $types = [
            ['value' => 'business', 'label' => 'Business', 'icon' => '🏢'],
            ['value' => 'individual', 'label' => 'Individual', 'icon' => '👤'],
            ['value' => 'government', 'label' => 'Government', 'icon' => '🏛️'],
            ['value' => 'nonprofit', 'label' => 'Non-Profit', 'icon' => '🤝'],
        ];

        return $this->filterByQuery($types, $query);
    }

    private function getInvoiceStatuses(string $query): array
    {
        $statuses = [
            ['value' => 'draft', 'label' => 'Draft', 'icon' => '📝'],
            ['value' => 'sent', 'label' => 'Sent', 'icon' => '📤'],
            ['value' => 'paid', 'label' => 'Paid', 'icon' => '✅'],
            ['value' => 'overdue', 'label' => 'Overdue', 'icon' => '⚠️'],
            ['value' => 'cancelled', 'label' => 'Cancelled', 'icon' => '❌'],
        ];

        return $this->filterByQuery($statuses, $query);
    }

    private function getProductCategories(string $query): array
    {
        $categories = [
            ['value' => 'electronics', 'label' => 'Electronics', 'icon' => '📱'],
            ['value' => 'software', 'label' => 'Software', 'icon' => '💿'],
            ['value' => 'services', 'label' => 'Services', 'icon' => '🛠️'],
            ['value' => 'subscriptions', 'label' => 'Subscriptions', 'icon' => '🔄'],
            ['value' => 'physical_goods', 'label' => 'Physical Goods', 'icon' => '📦'],
        ];

        return $this->filterByQuery($categories, $query);
    }

    private function getProductUnits(string $query): array
    {
        $units = [
            ['value' => 'unit', 'label' => 'Unit', 'icon' => '1️⃣'],
            ['value' => 'hour', 'label' => 'Hour', 'icon' => '⏰'],
            ['value' => 'day', 'label' => 'Day', 'icon' => '📅'],
            ['value' => 'month', 'label' => 'Month', 'icon' => '🗓️'],
            ['value' => 'kg', 'label' => 'Kilogram', 'icon' => '⚖️'],
            ['value' => 'license', 'label' => 'License', 'icon' => '📜'],
        ];

        return $this->filterByQuery($units, $query);
    }

    private function filterByQuery(array $items, string $query): array
    {
        if ($query === '') {
            return array_slice($items, 0, 9);
        }

        $query = strtolower($query);
        $filtered = array_filter($items, function ($item) use ($query) {
            return str_contains(strtolower($item['value']), $query)
                || str_contains(strtolower($item['label']), $query);
        });

        return array_slice(array_values($filtered), 0, 9);
    }
}
