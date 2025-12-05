<?php

namespace App\Http\Controllers\Api;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PaletteFlagController extends Controller
{
    /**
     * Get available values for command flags with optional entity search
     */
    public function flagValues(Request $request): JsonResponse
    {
        $flag = $request->get('flag');
        // Ensure query is always a string, even if null/empty
        $query = (string) ($request->get('q') ?? $request->get('query') ?? '');
        $searchEntity = $request->get('search_entity');
        $entity = $request->get('entity');
        $verb = $request->get('verb');

        // If searchEntity is provided, search the database
        if ($searchEntity) {
            $values = $this->searchEntity($searchEntity, $query);
            return response()->json(['values' => $values]);
        }

        // Otherwise use static values
        $values = match ($flag) {
            'status' => $this->getStatusValues($query),
            'currency' => $this->getCurrencyValues($query),
            'period' => $this->getPeriodValues($query),
            'type' => $this->getTypeValues($query),
            'method', 'payment_method' => $this->getPaymentMethodValues($query),
            default => [],
        };

        return response()->json(['values' => $values]);
    }

    /**
     * Search entities in the database
     */
    private function searchEntity(string $entityType, string $query): array
    {
        // Company search doesn't require a company context
        if ($entityType === 'company') {
            return $this->searchCompanies($query);
        }

        // All other entity searches require company context
        $company = CompanyContext::getCompany();
        if (!$company) {
            return [];
        }

        return match ($entityType) {
            'customer' => $this->searchCustomers($company->id, $query),
            'invoice' => $this->searchInvoices($company->id, $query),
            'vendor' => $this->searchVendors($company->id, $query),
            'bill' => $this->searchBills($company->id, $query),
            default => [],
        };
    }

    private function searchCustomers(string $companyId, string $query): array
    {
        $builder = DB::table('acct.customers')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->select('id', 'name', 'email', 'base_currency')
            ->orderBy('name')
            ->limit(10);

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('name', 'ilike', "%{$query}%")
                    ->orWhere('email', 'ilike', "%{$query}%");
            });
        }

        return $builder->get()->map(fn($row) => [
            'value' => $row->id,
            'label' => $row->name,
            'meta' => $row->email ?? $row->base_currency,
            'icon' => '👤',
        ])->all();
    }

    private function searchInvoices(string $companyId, string $query): array
    {
        $builder = DB::table('acct.invoices as i')
            ->leftJoin('acct.customers as c', 'i.customer_id', '=', 'c.id')
            ->where('i.company_id', $companyId)
            ->whereNull('i.deleted_at')
            ->select('i.id', 'i.invoice_number', 'i.total_amount', 'i.currency', 'i.status', 'c.name as customer_name')
            ->orderByDesc('i.created_at')
            ->limit(10);

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('i.invoice_number', 'ilike', "%{$query}%")
                    ->orWhere('c.name', 'ilike', "%{$query}%");
            });
        }

        return $builder->get()->map(fn($row) => [
            'value' => $row->id,
            'label' => $row->invoice_number ?: 'Draft',
            'meta' => ($row->customer_name ? $row->customer_name . ' - ' : '') . number_format((float)$row->total_amount, 2) . ' ' . $row->currency,
            'icon' => match($row->status) {
                'paid' => '✅',
                'sent' => '📤',
                'overdue' => '⚠️',
                'void', 'cancelled' => '❌',
                default => '📄',
            },
        ])->all();
    }

    private function searchCompanies(string $query): array
    {
        $userId = auth()->id();
        if (!$userId) {
            return [];
        }

        $builder = DB::table('auth.company_user as cu')
            ->join('auth.companies as c', 'cu.company_id', '=', 'c.id')
            ->where('cu.user_id', $userId)
            ->where('cu.is_active', true)
            ->where('c.is_active', true)
            ->select('c.id', 'c.name', 'c.slug', 'c.base_currency')
            ->orderBy('c.name')
            ->limit(10);

        if ($query) {
            $builder->where(function ($q) use ($query) {
                $q->where('c.name', 'ilike', "%{$query}%")
                    ->orWhere('c.slug', 'ilike', "%{$query}%");
            });
        }

        return $builder->get()->map(fn($row) => [
            'value' => $row->slug,
            'label' => $row->name,
            'meta' => $row->base_currency,
            'icon' => '🏢',
        ])->all();
    }

    private function searchVendors(string $companyId, string $query): array
    {
        // Check if vendors table exists
        try {
            $builder = DB::table('acct.vendors')
                ->where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->limit(10);

            if ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('name', 'ilike', "%{$query}%")
                        ->orWhere('email', 'ilike', "%{$query}%");
                });
            }

            return $builder->get()->map(fn($row) => [
                'value' => $row->id,
                'label' => $row->name,
                'meta' => $row->email ?? '',
                'icon' => '🏭',
            ])->all();
        } catch (\Exception $e) {
            // Table doesn't exist yet
            return [];
        }
    }

    private function searchBills(string $companyId, string $query): array
    {
        // Check if bills table exists
        try {
            $builder = DB::table('acct.bills as b')
                ->leftJoin('acct.vendors as v', 'b.vendor_id', '=', 'v.id')
                ->where('b.company_id', $companyId)
                ->whereNull('b.deleted_at')
                ->select('b.id', 'b.bill_number', 'b.total_amount', 'b.currency', 'b.status', 'v.name as vendor_name')
                ->orderByDesc('b.created_at')
                ->limit(10);

            if ($query) {
                $builder->where(function ($q) use ($query) {
                    $q->where('b.bill_number', 'ilike', "%{$query}%")
                        ->orWhere('v.name', 'ilike', "%{$query}%");
                });
            }

            return $builder->get()->map(fn($row) => [
                'value' => $row->id,
                'label' => $row->bill_number ?: 'Draft',
                'meta' => ($row->vendor_name ? $row->vendor_name . ' - ' : '') . number_format((float)$row->total_amount, 2) . ' ' . $row->currency,
                'icon' => '📋',
            ])->all();
        } catch (\Exception $e) {
            // Table doesn't exist yet
            return [];
        }
    }

    private function getStatusValues(string $query): array
    {
        $statuses = [
            ['value' => 'active', 'label' => 'Active', 'icon' => '✅'],
            ['value' => 'inactive', 'label' => 'Inactive', 'icon' => '⏸️'],
            ['value' => 'pending', 'label' => 'Pending', 'icon' => '⏳'],
            ['value' => 'archived', 'label' => 'Archived', 'icon' => '📦'],
        ];

        if (!$query) {
            return $statuses;
        }

        $q = strtolower($query);
        return array_values(array_filter($statuses, fn($s) =>
            str_contains(strtolower($s['value']), $q) ||
            str_contains(strtolower($s['label']), $q)
        ));
    }

    private function getCurrencyValues(string $query): array
    {
        $currencies = [
            ['value' => 'USD', 'label' => 'USD - US Dollar', 'icon' => '🇺🇸'],
            ['value' => 'EUR', 'label' => 'EUR - Euro', 'icon' => '🇪🇺'],
            ['value' => 'GBP', 'label' => 'GBP - British Pound', 'icon' => '🇬🇧'],
            ['value' => 'PKR', 'label' => 'PKR - Pakistani Rupee', 'icon' => '🇵🇰'],
            ['value' => 'AED', 'label' => 'AED - UAE Dirham', 'icon' => '🇦🇪'],
            ['value' => 'SAR', 'label' => 'SAR - Saudi Riyal', 'icon' => '🇸🇦'],
            ['value' => 'INR', 'label' => 'INR - Indian Rupee', 'icon' => '🇮🇳'],
            ['value' => 'CAD', 'label' => 'CAD - Canadian Dollar', 'icon' => '🇨🇦'],
            ['value' => 'AUD', 'label' => 'AUD - Australian Dollar', 'icon' => '🇦🇺'],
            ['value' => 'JPY', 'label' => 'JPY - Japanese Yen', 'icon' => '🇯🇵'],
        ];

        if (!$query) {
            return $currencies;
        }

        $q = strtolower($query);
        return array_values(array_filter($currencies, fn($c) =>
            str_contains(strtolower($c['value']), $q) ||
            str_contains(strtolower($c['label']), $q)
        ));
    }

    private function getPeriodValues(string $query): array
    {
        $periods = [
            ['value' => 'today', 'label' => 'Today', 'icon' => '📅'],
            ['value' => 'this_week', 'label' => 'This Week', 'icon' => '📅'],
            ['value' => 'this_month', 'label' => 'This Month', 'icon' => '📅'],
            ['value' => 'this_quarter', 'label' => 'This Quarter', 'icon' => '📅'],
            ['value' => 'this_year', 'label' => 'This Year', 'icon' => '📅'],
            ['value' => 'yesterday', 'label' => 'Yesterday', 'icon' => '📅'],
            ['value' => 'last_week', 'label' => 'Last Week', 'icon' => '📅'],
            ['value' => 'last_month', 'label' => 'Last Month', 'icon' => '📅'],
            ['value' => 'last_quarter', 'label' => 'Last Quarter', 'icon' => '📅'],
            ['value' => 'last_year', 'label' => 'Last Year', 'icon' => '📅'],
        ];

        if (!$query) {
            return $periods;
        }

        $q = strtolower($query);
        return array_values(array_filter($periods, fn($p) =>
            str_contains(strtolower($p['value']), $q) ||
            str_contains(strtolower($p['label']), $q)
        ));
    }

    private function getTypeValues(string $query): array
    {
        $types = [
            ['value' => 'business', 'label' => 'Business', 'icon' => '🏢'],
            ['value' => 'individual', 'label' => 'Individual', 'icon' => '👤'],
            ['value' => 'government', 'label' => 'Government', 'icon' => '🏛️'],
            ['value' => 'non_profit', 'label' => 'Non-Profit', 'icon' => '🤝'],
        ];

        if (!$query) {
            return $types;
        }

        $q = strtolower($query);
        return array_values(array_filter($types, fn($t) =>
            str_contains(strtolower($t['value']), $q) ||
            str_contains(strtolower($t['label']), $q)
        ));
    }

    private function getPaymentMethodValues(string $query): array
    {
        $methods = [
            ['value' => 'cash', 'label' => 'Cash', 'icon' => '💵'],
            ['value' => 'bank_transfer', 'label' => 'Bank Transfer', 'icon' => '🏦'],
            ['value' => 'card', 'label' => 'Card', 'icon' => '💳'],
            ['value' => 'cheque', 'label' => 'Cheque', 'icon' => '📝'],
            ['value' => 'other', 'label' => 'Other', 'icon' => '📋'],
        ];

        if (!$query) {
            return $methods;
        }

        $q = strtolower($query);
        return array_values(array_filter($methods, fn($m) =>
            str_contains(strtolower($m['value']), $q) ||
            str_contains(strtolower($m['label']), $q)
        ));
    }
}