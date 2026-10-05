<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;

/**
 * Who issued this document.
 *
 * Every document the application produces names its issuer, and the identity is
 * the same one every time: name, logo, address, how to reach them, and the tax
 * number the document is filed under. That identity was previously assembled by
 * whichever controller happened to be rendering -- which is why the invoice
 * screen shipped a name and a slug, the bill screen added a logo, and neither
 * carried an address at all.
 *
 * It is gathered from three places because that is where the data lives, and
 * pulling it together is precisely the job this class exists to do:
 *
 *   auth.companies                  name, trade name, logo, postal address
 *   auth.companies.settings         contact email and phone (contracted keys)
 *   acct.company_tax_registrations  the active NTN / STRN and what to call it
 *
 * The shape it returns is the shape `LedgerDocument`'s `issuer` prop expects,
 * so a controller passes it straight through without reshaping.
 */
class CompanyLetterhead
{
    /** @return array<string, mixed> */
    public function forCompany(Company $company): array
    {
        $settings = is_array($company->settings) ? $company->settings : [];

        $registration = DB::table('acct.company_tax_registrations')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->first(['registration_number', 'registration_type']);

        return [
            'name' => $company->trade_name ?: $company->name,
            // A trade name is what the company is called; the registered name is
            // who it is. When they differ the document needs both, because the
            // second is the one an auditor matches against.
            'legalName' => $company->trade_name && $company->trade_name !== $company->name
                ? $company->name
                : null,
            'logoUrl' => $company->logo_url,
            'stampUrl' => $this->publicUrl($company->stamp_path),
            'signatureUrl' => $this->publicUrl($company->signature_path),
            'signerName' => $company->signer_name,
            'signerTitle' => $company->signer_title,
            'stampDocuments' => $company->stampDocumentFlags(),
            'lines' => $this->addressLines($company->address),
            'email' => $settings['contact_email'] ?? null,
            'phone' => $settings['contact_phone'] ?? null,
            'taxId' => $registration?->registration_number,
            'taxIdLabel' => $this->taxLabel($registration?->registration_type),
        ];
    }

    /**
     * The stamp block for ONE document, or null. Only a final document (not
     * draft / void / cancelled / reversed) of a ticked type carries it, so the
     * rule lives here and every page asks the same question. $docType is a key
     * of Company::STAMP_DEFAULTS; $documentDate is the document's own date.
     *
     * @return array{stampUrl: ?string, signatureUrl: ?string, signerName: ?string, signerTitle: ?string}|null
     */
    public function stampFor(Company $company, string $docType, bool $final = true, \DateTimeInterface|string|null $documentDate = null): ?array
    {
        if (! $final || ! ($company->stampDocumentFlags()[$docType] ?? false)) {
            return null;
        }

        // Only documents dated on or after stamp_from carry it; older ones stay as issued.
        if ($company->stamp_from && $documentDate) {
            $date = $documentDate instanceof \DateTimeInterface ? $documentDate->format('Y-m-d') : substr((string) $documentDate, 0, 10);
            if ($date < $company->stamp_from->format('Y-m-d')) {
                return null;
            }
        }

        $stamp = $this->publicUrl($company->stamp_path);
        $signature = $this->publicUrl($company->signature_path);
        if (! $stamp && ! $signature) {
            return null;
        }

        return [
            'stampUrl' => $stamp,
            'signatureUrl' => $signature,
            'signerName' => $company->signer_name,
            'signerTitle' => $company->signer_title,
        ];
    }

    /** Statuses a document cannot be stamped in. */
    public static function isFinalStatus(?string $status): bool
    {
        return ! in_array($status, ['draft', 'void', 'voided', 'cancelled', 'reversed'], true);
    }

    private function publicUrl(?string $path): ?string
    {
        return $path ? '/storage/'.ltrim($path, '/') : null;
    }

    /**
     * An address is stored loosely, because it is captured differently
     * depending on the screen it came from. Take the parts a postal address is
     * made of, in the order they are written, and drop whatever is absent
     * rather than printing an empty line under the company's name.
     *
     * @return array<int, string>
     */
    public function addressLines(mixed $address): array
    {
        if (! is_array($address)) {
            return [];
        }

        $parts = ['line1', 'line2', 'street', 'city', 'state', 'postal_code', 'country'];

        return array_values(array_filter(array_map(
            fn (string $part) => is_string($address[$part] ?? null) ? trim($address[$part]) : '',
            $parts,
        ), fn (string $line) => $line !== ''));
    }

    /**
     * Pakistan files sales tax under an STRN and income tax under an NTN, and a
     * document that prints the wrong label beside the right number is worse
     * than one that prints neither.
     */
    protected function taxLabel(?string $type): ?string
    {
        // The vocabulary is the one CreateTaxRegistrationRequest allows.
        return match ($type) {
            null => null,
            'sales_tax' => 'STRN',
            'withholding' => 'WHT',
            'other' => 'Tax no.',
            default => strtoupper($type),
        };
    }
}
