<?php

namespace App\Http\Controllers;

use App\Constants\Permissions;
use App\Facades\CompanyContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The one Settings page: everything a company sets up, in one place, grouped by what is being
 * set up (the company, the station, the accounts). It replaced a sidebar group of ten links and
 * a company settings page nothing linked to. Each entry shows only to someone allowed to use it.
 */
class SettingsHubController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $company = CompanyContext::getCompany();
        $user = $request->user();
        $can = fn (string $permission) => $user->isGodMode() || $user->hasCompanyPermission($permission);
        $slug = $company->slug;
        $fuel = $company->isModuleEnabled('fuel_station');
        $inventory = $company->isModuleEnabled('inventory');

        $item = fn (string $title, string $description, string $href, bool $allowed = true) => $allowed
            ? ['title' => $title, 'description' => $description, 'href' => "/{$slug}/{$href}"]
            : null;
        $section = fn (string $title, array $items) => ['title' => $title, 'items' => array_values(array_filter($items))];

        $sections = [
            $section('Company', [
                $item('Company details', 'Name, address, phone, logo, who signs invoices', 'settings?tab=general', $can(Permissions::COMPANY_UPDATE)),
                $item('Users & permissions', 'Who can sign in and what they can do', 'settings?tab=users', $can(Permissions::COMPANY_UPDATE)),
                $item('Currencies', 'Currencies you trade in', 'settings?tab=currencies', $can(Permissions::COMPANY_UPDATE)),
                $item('Modules', 'Turn features such as payroll and inventory on or off', 'settings?tab=modules', $can(Permissions::COMPANY_UPDATE)),
                $item('Fiscal year', 'Year start and accounting periods', 'settings?tab=accounting', $can(Permissions::COMPANY_UPDATE)),
            ]),
            $fuel ? $section('Station', [
                $item('Station settings', 'Features, payment channels and the accounts the daily close posts to', 'fuel/settings', $can(Permissions::COMPANY_UPDATE)),
                $item('Tanks', 'Tanks and their fuel', 'warehouses', $inventory && $can(Permissions::WAREHOUSE_VIEW)),
                $item('Pumps & nozzles', 'Pumps, nozzles and meters', 'fuel/pumps', $can(Permissions::PUMP_VIEW)),
                $item('Setup wizard', 'Step-by-step station setup', 'fuel/onboarding', $can(Permissions::COMPANY_UPDATE)),
            ]) : null,
            $section('Accounting', [
                $item('Chart of accounts', 'Every account in the books', 'accounts', $can(Permissions::ACCOUNT_VIEW)),
                $item('Default accounts', 'Receivables, payables, retained earnings and other company-wide accounts', 'accounting/default-accounts', $can(Permissions::ACCOUNT_UPDATE)),
                $item('Opening balances', 'Cash, banks, stock and balances on the start date', 'accounting/opening-balances', $can(Permissions::OPENING_BALANCE_VIEW)),
                $item('Tax', 'Tax registration and rates', 'tax/settings', $can(Permissions::TAX_VIEW)),
                $item('Posting templates', 'How documents post to the books', 'posting-templates', $can(Permissions::ACCOUNT_UPDATE)),
            ]),
            $company->isModuleEnabled('payroll') ? $section('Payroll', [
                $item('Earnings', 'Kinds of pay: overtime, bonus, allowances', 'earning-types', $can(Permissions::COMPANY_UPDATE)),
                $item('Deductions', 'Kinds of deduction: tax, fines, loan recovery', 'deduction-types', $can(Permissions::COMPANY_UPDATE)),
                $item('Leave types', 'Kinds of leave employees can take', 'leave-types', $can(Permissions::COMPANY_UPDATE)),
                $item('Leave requests', 'Leave asked for and approved', 'leave-requests', $can(Permissions::COMPANY_UPDATE)),
            ]) : null,
            $fuel ? $section('Help', [
                $item('Help guide', 'How the station works in Haasib', 'fuel/guide'),
            ]) : null,
        ];

        return Inertia::render('company/SettingsHub', [
            'company' => ['id' => $company->id, 'name' => $company->name, 'slug' => $slug],
            'sections' => array_values(array_filter($sections, fn ($s) => $s && $s['items'])),
        ]);
    }
}
