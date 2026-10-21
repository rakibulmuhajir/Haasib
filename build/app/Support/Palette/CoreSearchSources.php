<?php

namespace App\Support\Palette;

use Illuminate\Support\Facades\DB;

/** Search closures for the core entities a palette field can point at. */
final class CoreSearchSources
{
    public static function register(): void
    {
        PaletteSearchSources::register('customer', fn ($c, $q) => self::customers($c, $q));
        PaletteSearchSources::register('vendor', fn ($c, $q) => self::vendors($c, $q));
        PaletteSearchSources::register('bill', fn ($c, $q) => self::bills($c, $q));
        PaletteSearchSources::register('account', fn ($c, $q) => self::accounts($c, $q));
        PaletteSearchSources::register('item', fn ($c, $q) => self::items($c, $q));
        PaletteSearchSources::register('employee', fn ($c, $q) => self::employees($c, $q));
        PaletteSearchSources::register('warehouse', fn ($c, $q) => self::warehouses($c, $q));
        PaletteSearchSources::register('wht_rate', fn ($c, $q) => self::whtRates($c, $q));
    }

    /** @param list<string> $columns */
    private static function base(string $table, string $companyId, string $query, array $columns)
    {
        $builder = DB::table($table)->where('company_id', $companyId)->whereNull('deleted_at')->limit(10);

        if ($query !== '') {
            $builder->where(function ($q) use ($columns, $query) {
                foreach ($columns as $i => $col) {
                    $i === 0 ? $q->where($col, 'ilike', "%{$query}%") : $q->orWhere($col, 'ilike', "%{$query}%");
                }
            });
        }

        return $builder;
    }

    private static function customers(string $companyId, string $query): array
    {
        return self::base('acct.customers', $companyId, $query, ['name', 'email'])
            ->select('id', 'name', 'email', 'base_currency')->orderBy('name')->get()
            ->map(fn ($r) => ['value' => $r->id, 'label' => $r->name, 'meta' => $r->email ?? $r->base_currency, 'icon' => '👤'])->all();
    }

    private static function vendors(string $companyId, string $query): array
    {
        return self::base('acct.vendors', $companyId, $query, ['name', 'vendor_number'])
            ->select('id', 'name', 'vendor_number')->orderBy('name')->get()
            ->map(fn ($r) => ['value' => $r->id, 'label' => $r->name, 'meta' => $r->vendor_number, 'icon' => '🏪'])->all();
    }

    private static function bills(string $companyId, string $query): array
    {
        return self::base('acct.bills', $companyId, $query, ['bill_number'])
            ->select('id', 'bill_number', 'total_amount', 'currency')->orderByDesc('created_at')->get()
            ->map(fn ($r) => [
                'value' => $r->id,
                'label' => $r->bill_number ?: 'Draft',
                'meta' => number_format((float) $r->total_amount, 2).' '.$r->currency,
                'icon' => '🧾',
            ])->all();
    }

    private static function accounts(string $companyId, string $query): array
    {
        return self::base('acct.accounts', $companyId, $query, ['code', 'name'])
            ->where('is_active', true)
            ->select('id', 'code', 'name', 'type')->orderBy('code')->get()
            ->map(fn ($r) => ['value' => $r->id, 'label' => $r->code.' '.$r->name, 'meta' => $r->type, 'icon' => '📒'])->all();
    }

    private static function items(string $companyId, string $query): array
    {
        return self::base('inv.items', $companyId, $query, ['sku', 'name'])
            ->select('id', 'sku', 'name')->orderBy('name')->get()
            ->map(fn ($r) => ['value' => $r->id, 'label' => $r->name, 'meta' => $r->sku, 'icon' => '📦'])->all();
    }

    private static function employees(string $companyId, string $query): array
    {
        return self::base('pay.employees', $companyId, $query, ['first_name', 'last_name', 'employee_number'])
            ->select('id', 'first_name', 'last_name', 'employee_number')->orderBy('first_name')->get()
            ->map(fn ($r) => ['value' => $r->id, 'label' => trim($r->first_name.' '.$r->last_name), 'meta' => $r->employee_number, 'icon' => '🧑'])->all();
    }

    /** Withholding rates only: the sales and purchase rates are not offered for tax withheld. */
    private static function whtRates(string $companyId, string $query): array
    {
        return self::base('acct.tax_rates', $companyId, $query, ['name', 'code', 'section'])
            ->where('tax_type', 'withholding')->where('is_active', true)
            ->select('id', 'name', 'section', 'rate', 'rate_non_filer')->orderBy('name')->get()
            ->map(fn ($r) => [
                'value' => $r->id,
                'label' => $r->name,
                'meta' => trim(($r->section ? $r->section.' · ' : '').rtrim(rtrim(number_format((float) $r->rate, 4), '0'), '.').'%'
                    .($r->rate_non_filer !== null ? ' / '.rtrim(rtrim(number_format((float) $r->rate_non_filer, 4), '0'), '.').'%' : '')),
                'icon' => '%',
            ])->all();
    }

    private static function warehouses(string $companyId, string $query): array
    {
        return self::base('inv.warehouses', $companyId, $query, ['code', 'name'])
            ->select('id', 'code', 'name')->orderBy('name')->get()
            ->map(fn ($r) => ['value' => $r->id, 'label' => $r->name, 'meta' => $r->code, 'icon' => '🏬'])->all();
    }
}
