<?php

namespace App\Services;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\User;

/**
 * Everything a company sets up, grouped, for the Settings menu. A group is one place to go (href)
 * or a set of pages; its items are what is inside -- a page's tabs or parts, or separate pages.
 * Each entry shows only to someone allowed to use it. (Replaces the old Settings home page.)
 */
class SettingsMenu
{
    /** @return array<int, array{title:string, href:?string, items:array<int, array{title:string, description:string, href:string}>}> */
    public function sections(Company $company, User $user): array
    {
        $can = fn (string $permission) => $user->isGodMode() || $user->hasCompanyPermission($permission);
        $slug = $company->slug;
        $fuel = $company->isModuleEnabled('fuel_station');
        $inventory = $company->isModuleEnabled('inventory');

        // A section is one place to go (href) or a set of pages; its items are what is inside --
        // tabs or parts of that page, or the separate pages -- listed under it, each a link.
        $item = fn (string $title, string $description, string $href, bool $allowed = true) => $allowed
            ? ['title' => $title, 'description' => $description, 'href' => "/{$slug}/{$href}"]
            : null;
        $section = fn (string $title, array $items, ?string $href = null) => [
            'title' => $title,
            'href' => $href ? "/{$slug}/{$href}" : null,
            'items' => array_values(array_filter($items)),
        ];
        $canCompany = $can(Permissions::COMPANY_UPDATE);

        $sections = [
            $section('Company', [
                $item('General', 'Name, address, phone, logo, who signs invoices', 'settings?tab=general', $canCompany),
                $item('Users & permissions', 'Who can sign in and what they can do', 'settings?tab=users', $canCompany),
                $item('Currencies', 'Currencies you trade in', 'settings?tab=currencies', $canCompany),
                $item('Modules', 'Turn features such as payroll and inventory on or off', 'settings?tab=modules', $canCompany),
                $item('Fiscal year', 'Year start and accounting periods', 'settings?tab=accounting', $canCompany),
            ], $canCompany ? 'settings' : null),
            $fuel ? $section('Station settings', [
                $item('General', 'Features the station uses', 'fuel/settings#general', $canCompany),
                $item('Monthly fuel profit', 'How month-end stock is valued', 'fuel/settings#monthly-profit', $canCompany),
                $item('Payment channels', 'Cards, banks and wallets the close takes', 'fuel/settings#payment-channels', $canCompany),
                $item('Account mappings', 'The accounts the daily close posts to', 'fuel/settings#accounts', $canCompany),
            ], $canCompany ? 'fuel/settings' : null) : null,
            $fuel ? $section('Station equipment', [
                $item('Tanks', 'Tanks and their fuel', 'warehouses', $inventory && $can(Permissions::WAREHOUSE_VIEW)),
                $item('Pumps & nozzles', 'Pumps, nozzles and meters', 'fuel/pumps', $can(Permissions::PUMP_VIEW)),
                $item('Setup wizard', 'Step-by-step station setup', 'fuel/onboarding', $canCompany),
            ]) : null,
            $section('Accounting', [
                $item('Chart of accounts', 'Every account in the books', 'accounts', $can(Permissions::ACCOUNT_VIEW)),
                $item('Default accounts', 'Receivables, payables, retained earnings and other company-wide accounts', 'accounting/default-accounts', $can(Permissions::ACCOUNT_UPDATE)),
                $item('Opening balances', 'Cash, banks, stock and balances on the start date', 'accounting/opening-balances', $can(Permissions::OPENING_BALANCE_VIEW)),
                $item('Tax', 'Tax registration and rates', 'tax/settings', $can(Permissions::TAX_VIEW)),
                $item('Posting templates', 'How documents post to the books', 'posting-templates', $can(Permissions::ACCOUNT_UPDATE)),
            ]),
            $company->isModuleEnabled('payroll') ? $section('Payroll', [
                $item('Earnings', 'Kinds of pay: overtime, bonus, allowances', 'earning-types', $canCompany),
                $item('Deductions', 'Kinds of deduction: tax, fines, loan recovery', 'deduction-types', $canCompany),
                $item('Leave types', 'Kinds of leave employees can take', 'leave-types', $canCompany),
                $item('Leave requests', 'Leave asked for and approved', 'leave-requests', $canCompany),
            ]) : null,
            $fuel ? $section('Help', [
                $item('Help guide', 'How the station works in Haasib', 'fuel/guide'),
            ]) : null,
        ];

        return array_values(array_filter($sections, fn ($s) => $s && $s['items']));
    }
}
