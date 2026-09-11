<?php

namespace App\Modules\Umrah\Services;

use App\Models\Company;
use App\Modules\Umrah\Models\Voucher;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class VoucherPrintDocument
{
    public function __construct(private VoucherServiceOrigins $origins) {}

    public function payload(Company $company, Voucher $voucher): array
    {
        $hasExternalSources = $this->origins->hasExternalSources($voucher);
        $originServices = $hasExternalSources ? $this->origins->printRows($voucher) : [];
        $hasVisa = in_array($voucher->service_bundle, ['visa', 'visa_hotel', 'visa_transport', 'visa_transport_hotel'], true);
        $hasTransport = in_array($voucher->service_bundle, ['transport', 'transport_hotel', 'visa_transport', 'visa_transport_hotel'], true);
        $parties = [
            ['role' => 'Company', 'name' => $company->trade_name ?: $company->name, 'logo' => $this->logo($company->logo_url)],
            ['role' => 'Agent', 'name' => $voucher->agent?->name, 'logo' => $this->logo($voucher->agent?->logo_url)],
            ['role' => 'Visa provider', 'name' => $hasVisa ? $voucher->group?->vendor?->name : null, 'logo' => $hasVisa ? $this->logo($voucher->group?->vendor?->logo_url) : null],
            ['role' => 'Transport provider', 'name' => $hasTransport ? $voucher->group?->mandatoryTransportVendor?->name : null, 'logo' => $hasTransport ? $this->logo($voucher->group?->mandatoryTransportVendor?->logo_url) : null],
        ];
        if ($hasExternalSources) {
            // Preserve the four header slots; never attribute everyone to the
            // destination agent's providers or add an unbounded wall of logos.
            $parties[2] = ['role' => 'Visa providers', 'name' => 'See passenger services', 'logo' => null];
            $parties[3] = ['role' => 'Transport providers', 'name' => 'See passenger services', 'logo' => null];
        }
        $writer = new Writer(new ImageRenderer(new RendererStyle(180, 4), new SvgImageBackEnd));
        $mapCodes = [];
        foreach ($voucher->hotel_stays ?? [] as $index => $stay) {
            $url = $stay['map_url'] ?? '';
            if (is_string($url) && strlen($url) <= 500 && preg_match('~^https://(www\.google\.com/maps/|maps\.google\.com/|maps\.app\.goo\.gl/|goo\.gl/maps/)~i', $url)) {
                $mapCodes[$index] = 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($url));
            }
        }

        return compact('parties', 'mapCodes', 'hasExternalSources', 'originServices');
    }

    private function logo(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        if (preg_match('~^data:image/(png|jpeg|gif);base64,~', $url)) {
            return $url;
        }
        if (! str_starts_with($url, '/storage/')) {
            return null;
        }
        $root = realpath(storage_path('app/public'));
        $path = realpath(storage_path('app/public/'.substr($url, 9)));
        if (! $root || ! $path || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return null;
        }
        $mime = mime_content_type($path);

        return in_array($mime, ['image/png', 'image/jpeg', 'image/gif'], true)
            ? 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path)) : null;
    }
}
