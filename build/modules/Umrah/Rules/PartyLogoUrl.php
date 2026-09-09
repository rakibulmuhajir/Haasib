<?php

namespace App\Modules\Umrah\Rules;

use App\Services\CurrentCompany;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PartyLogoUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $company = app(CurrentCompany::class)->get();
        $prefix = '/storage/party-logos/'.$company?->id.'/';
        if ($company && is_string($value) && str_starts_with($value, $prefix)
            && preg_match('/^[0-9a-f-]{36}\.png$/iD', substr($value, strlen($prefix)))) {
            return;
        }
        // Preserve existing externally linked logos without accepting unsafe schemes.
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)
            && in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return;
        }
        $fail('Choose an uploaded logo from this company or a valid HTTP/HTTPS logo URL.');
    }
}
