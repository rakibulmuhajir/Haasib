<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\VisaVendor;

class VoucherPrintProfiles
{
    public static function rules(string $key): array
    {
        return [
            $key => ['sometimes', 'array:footer_text,contacts', 'required_array_keys:contacts'],
            "$key.footer_text" => ['nullable', 'string', 'max:2000'],
            "$key.contacts" => ['sometimes', 'array', 'list', 'max:12'],
            "$key.contacts.*" => ['array:name,responsibility,organization,phone,whatsapp,city'],
            "$key.contacts.*.name" => ['required', 'string', 'max:150'],
            "$key.contacts.*.responsibility" => ['required', 'string', 'max:100'],
            "$key.contacts.*.organization" => ['nullable', 'string', 'max:150'],
            "$key.contacts.*.phone" => ['required', 'string', 'max:50'],
            "$key.contacts.*.whatsapp" => ['nullable', 'string', 'max:50'],
            "$key.contacts.*.city" => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function attributes(string $key): array
    {
        return [
            "$key.footer_text" => 'voucher footer',
            "$key.contacts" => 'contacts',
            "$key.contacts.*.name" => 'representative name',
            "$key.contacts.*.responsibility" => 'responsibility',
            "$key.contacts.*.organization" => 'company / agent',
            "$key.contacts.*.phone" => 'phone number',
            "$key.contacts.*.whatsapp" => 'WhatsApp number',
            "$key.contacts.*.city" => 'city',
        ];
    }

    public function defaults(Company $company, ?string $agentId): array
    {
        $companyProfile = $company->settings['umrah_voucher'] ?? [];
        $agentProfile = $agentId ? Agent::where('company_id', $company->id)->find($agentId)?->voucher_settings : [];

        return [
            'footer_text' => filled($agentProfile['footer_text'] ?? null) ? $agentProfile['footer_text'] : ($companyProfile['footer_text'] ?? ''),
            'contacts' => array_slice(array_merge($companyProfile['contacts'] ?? [], $agentProfile['contacts'] ?? []), 0, 12),
        ];
    }

    public function catalog(Company $company, ?string $agentId = null, bool $allAgents = false): array
    {
        $profiles = [['key' => 'company', 'label' => $company->name.' · Company', 'details' => $company->settings['umrah_voucher'] ?? ['footer_text' => '', 'contacts' => []]]];
        $agents = Agent::where('company_id', $company->id)->with('customer:id,name')->where('is_active', true)
            ->when(! $allAgents, fn ($query) => $query->whereKey($agentId))->get();
        foreach ($agents as $agent) {
            $profiles[] = ['key' => 'agent:'.$agent->id, 'label' => $agent->name.' · Agent', 'details' => $agent->voucher_settings ?? ['footer_text' => '', 'contacts' => []]];
        }
        foreach (VisaVendor::where('company_id', $company->id)->with('vendor:id,name')->where('is_active', true)->get() as $vendor) {
            $profiles[] = ['key' => 'vendor:'.$vendor->id, 'label' => $vendor->name.' · '.($vendor->service_type === VisaVendor::SERVICE_TRANSPORT_PROVIDER ? 'Transport' : 'Visa provider'), 'details' => $vendor->voucher_settings ?? ['footer_text' => '', 'contacts' => []]];
        }

        return $profiles;
    }
}
