<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pakistan's banks in the shared bank list.
 *
 * acct.banks is reference data offered when adding a bank account. It was seeded with four Saudi
 * banks and nothing else, while every company actually using the app is in Pakistan - so the
 * Bank list on a Pakistani station's bank account form offered Riyad Bank and nothing it banks
 * with.
 *
 * A migration rather than a seeder because deploys run migrations: this is reference data every
 * environment needs, not demo data. Idempotent - a bank already listed under PK by the same name
 * is left alone - since the table has no unique key to lean on.
 *
 * SWIFT codes are given only where they are well established; the rest are null rather than
 * guessed, because a wrong code on a payment is worse than a missing one. The bank is optional on
 * the account form, so a bank missing here never blocks anyone.
 */
return new class extends Migration
{
    private const BANKS = [
        ['Allied Bank Limited', 'ABPAPKKA'],
        ['Askari Bank Limited', 'ASCMPKKA'],
        ['Bank Al Habib Limited', 'BAHLPKKA'],
        ['Bank Alfalah Limited', 'ALFHPKKA'],
        ['BankIslami Pakistan Limited', 'BKIPPKKA'],
        ['Dubai Islamic Bank Pakistan Limited', 'DUIBPKKA'],
        ['Faysal Bank Limited', 'FAYSPKKA'],
        ['Habib Bank Limited (HBL)', 'HABBPKKA'],
        ['Habib Metropolitan Bank Limited', 'MPBLPKKA'],
        ['JS Bank Limited', 'JSBLPKKA'],
        ['MCB Bank Limited', 'MUCBPKKA'],
        ['MCB Islamic Bank Limited', null],
        ['Meezan Bank Limited', 'MEZNPKKA'],
        ['National Bank of Pakistan', 'NBPAPKKA'],
        ['Soneri Bank Limited', 'SONEPKKA'],
        ['Standard Chartered Bank (Pakistan) Limited', 'SCBLPKKX'],
        ['The Bank of Khyber', 'KHYBPKKA'],
        ['The Bank of Punjab', 'BPUNPKKA'],
        ['Sindh Bank Limited', 'SINDPKKA'],
        ['United Bank Limited (UBL)', 'UNILPKKA'],
        ['Al Baraka Bank (Pakistan) Limited', 'AIINPKKA'],
        ['Silkbank Limited', 'SAUDPKKA'],
        ['Samba Bank Limited', 'SAMBPKKA'],
        ['Citibank N.A. Pakistan', 'CITIPKKX'],
        ['Industrial and Commercial Bank of China (Pakistan)', 'ICBKPKKA'],
        ['Bank of China (Pakistan)', 'BKCHPKKA'],
        ['Deutsche Bank AG Pakistan', 'DEUTPKKA'],
        ['First Women Bank Limited', null],
        ['Zarai Taraqiati Bank Limited', null],
        ['Mobilink Microfinance Bank (JazzCash)', null],
        ['Telenor Microfinance Bank (Easypaisa)', null],
    ];

    public function up(): void
    {
        $listed = DB::table('acct.banks')->where('country_code', 'PK')->pluck('name')->all();

        $rows = [];
        foreach (self::BANKS as [$name, $swift]) {
            if (in_array($name, $listed, true)) {
                continue;
            }
            $rows[] = [
                'name' => $name,
                'swift_code' => $swift,
                'country_code' => 'PK',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows) {
            DB::table('acct.banks')->insert($rows);
        }
    }

    public function down(): void
    {
        // Only the rows this added, and only those no bank account points at: removing a bank
        // an account uses would silently blank that account's bank.
        DB::table('acct.banks as b')
            ->where('b.country_code', 'PK')
            ->whereIn('b.name', array_column(self::BANKS, 0))
            ->whereNotExists(fn ($q) => $q->from('acct.company_bank_accounts as a')->whereColumn('a.bank_id', 'b.id'))
            ->delete();
    }
};
