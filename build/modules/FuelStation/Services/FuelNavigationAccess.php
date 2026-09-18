<?php

namespace App\Modules\FuelStation\Services;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\User;
use App\Modules\FuelStation\Models\StationSettings;

class FuelNavigationAccess
{
    public function forUser(Company $company, User $user): array
    {
        $permissions = [
            'dailyClose' => Permissions::DAILY_CLOSE_CREATE,
            'closeHistory' => Permissions::DAILY_CLOSE_VIEW,
            'stock' => Permissions::STOCK_VIEW,
            'deliveries' => Permissions::STOCK_VIEW,
            'prices' => Permissions::FUEL_RATE_UPDATE,
            'products' => Permissions::ITEM_VIEW,
            'bills' => Permissions::BILL_VIEW,
            'vendors' => Permissions::VENDOR_VIEW,
            'settlements' => Permissions::PAYMENT_CREATE,
            'customers' => Permissions::CUSTOMER_VIEW,
            'payments' => Permissions::PAYMENT_VIEW,
            'expenses' => Permissions::EXPENSE_VIEW,
            'employees' => Permissions::EMPLOYEE_VIEW,
            'payroll' => Permissions::PAYROLL_RUN_VIEW,
            'partners' => Permissions::ACCOUNT_VIEW,
            'investors' => Permissions::INVESTOR_VIEW,
            'reports' => Permissions::REPORT_VIEW,
            'settings' => Permissions::COMPANY_UPDATE,
            'warehouses' => Permissions::WAREHOUSE_VIEW,
            'pumps' => Permissions::PUMP_VIEW,
            'banking' => Permissions::BANK_ACCOUNT_VIEW,
            'bankFeed' => Permissions::BANK_FEED_VIEW,
            'bankReconciliation' => Permissions::BANK_RECONCILIATION_VIEW,
            'creditNotes' => Permissions::CREDIT_NOTE_VIEW,
            'journals' => Permissions::JOURNAL_VIEW,
            'accounts' => Permissions::ACCOUNT_VIEW,
            'accountSettings' => Permissions::ACCOUNT_UPDATE,
        ];

        return [
            'allowed' => array_keys(array_filter($permissions,
                fn (string $permission): bool => $user->isGodMode() || $user->hasCompanyPermission($permission))),
            'hasInvestors' => (bool) StationSettings::where('company_id', $company->id)->value('has_investors'),
        ];
    }
}
