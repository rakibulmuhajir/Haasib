<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * The supplier side of the same change: an umrah vendor becomes an extension
 * of an acct.vendor rather than a second supplier standing beside one.
 *
 * umrah.visa_vendors carried its own name, phone, email and logo_url. Those are
 * the four facts acct.vendors already holds, under the same names, at the same
 * widths -- and unlike the agent, this table did not even have a column to link
 * the two, so a visa provider that also billed the company through accounting
 * appeared twice with two balances and nothing to say they were one supplier.
 *
 * What stays here is what belongs to the umrah desk and nowhere else: the
 * per-passenger visa and bus pricing, whether the vendor is company-owned,
 * whether it provides mandatory transport, and city -- a vendor's address is a
 * single free-text line, an umrah vendor's city is the departure city the desk
 * dispatches from.
 *
 * vendor_number stays too, for the same reason agent_number did: it is the
 * umrah desk's own reference for its own register, not a copy of the
 * accounting one.
 *
 * vendor_type stays as well, and is deliberately NOT merged into
 * Vendor::TYPES. The two answer different questions -- the accounting type says
 * how a supplier is bought from, the umrah one says what it provides to a
 * group -- and ten validation rules select transport providers by it. Giving
 * it a name that says so is worth doing and is not this migration; it would
 * touch seventy sites and several of those rules fail closed, which is how a
 * legitimate booking gets refused.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->uuid('vendor_id')->nullable()->after('company_id');
            $table->foreign('vendor_id')
                ->references('id')
                ->on('acct.vendors')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        $this->backfillVendors();

        DB::statement('ALTER TABLE umrah.visa_vendors ALTER COLUMN vendor_id SET NOT NULL');

        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            // One vendor is one umrah vendor. Without this the split the
            // migration just closed could be reopened by a second row.
            $table->unique('vendor_id');
            $table->dropColumn(['name', 'phone', 'email', 'logo_url']);
        });
    }

    /**
     * Give every umrah vendor an accounting vendor before the column is made
     * NOT NULL.
     *
     * A name that already answers in this company's supplier register is that
     * supplier. Matching on the name rather than the email because these rows
     * are mostly government offices and visa desks entered without one, and a
     * duplicate here would recreate the split this migration exists to close.
     */
    private function backfillVendors(): void
    {
        $umrahVendors = DB::table('umrah.visa_vendors')
            ->whereNull('vendor_id')
            ->orderBy('company_id')
            ->get(['id', 'company_id', 'name', 'email', 'phone', 'logo_url', 'created_at']);

        if ($umrahVendors->isEmpty()) {
            return;
        }

        $currencies = DB::table('auth.companies')->pluck('base_currency', 'id');

        foreach ($umrahVendors as $umrahVendor) {
            $vendorId = DB::table('acct.vendors')
                ->where('company_id', $umrahVendor->company_id)
                ->whereRaw('lower(name) = ?', [Str::lower((string) $umrahVendor->name)])
                ->whereNull('deleted_at')
                ->value('id');

            if (! $vendorId) {
                $vendorId = (string) Str::uuid();

                DB::table('acct.vendors')->insert([
                    'id' => $vendorId,
                    'company_id' => $umrahVendor->company_id,
                    'vendor_number' => $this->nextVendorNumber($umrahVendor->company_id),
                    'name' => $umrahVendor->name,
                    'vendor_type' => 'service_provider',
                    'email' => $umrahVendor->email,
                    'phone' => $umrahVendor->phone,
                    'logo_url' => $umrahVendor->logo_url,
                    'base_currency' => $currencies[$umrahVendor->company_id] ?? 'PKR',
                    'payment_terms' => 30,
                    'is_active' => true,
                    // The supplier has existed since the umrah row did, not
                    // since this migration ran; ageing reports read created_at.
                    'created_at' => $umrahVendor->created_at,
                    'updated_at' => now(),
                ]);
            }

            DB::table('umrah.visa_vendors')
                ->where('id', $umrahVendor->id)
                ->update(['vendor_id' => $vendorId]);
        }
    }

    /**
     * The same VEND-00001 shape Vendor\CreateAction generates. Read fresh each
     * time rather than counted once up front, because the rows this loop
     * inserts have to be counted too.
     */
    private function nextVendorNumber(string $companyId): string
    {
        $last = DB::table('acct.vendors')
            ->where('company_id', $companyId)
            ->whereNotNull('vendor_number')
            ->orderByDesc('vendor_number')
            ->value('vendor_number');

        $sequence = ($last && preg_match('/(\d+)$/', $last, $m)) ? ((int) $m[1]) + 1 : 1;

        return 'VEND-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public function down(): void
    {
        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_url', 500)->nullable();
        });

        // Put the supplier's details back where they were, so the old code can
        // read them, before the link stops being guaranteed.
        DB::statement('
            UPDATE umrah.visa_vendors v
               SET name = a.name, phone = a.phone, email = a.email, logo_url = a.logo_url
              FROM acct.vendors a
             WHERE a.id = v.vendor_id
        ');

        DB::statement("UPDATE umrah.visa_vendors SET name = '' WHERE name IS NULL");
        DB::statement('ALTER TABLE umrah.visa_vendors ALTER COLUMN name SET NOT NULL');

        Schema::table('umrah.visa_vendors', function (Blueprint $table) {
            $table->dropUnique(['vendor_id']);
            $table->dropForeign(['vendor_id']);
            $table->dropColumn('vendor_id');
        });
    }
};
