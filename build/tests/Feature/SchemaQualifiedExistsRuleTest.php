<?php

use App\Models\Company;
use App\Modules\Accounting\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * exists/unique rules naming a schema-qualified table ("fuel.nozzles", "acct.accounts") must
 * query the default connection - the one carrying the request's company context - never the
 * schema-named connection Laravel would otherwise read the prefix as. On production that
 * other session sees nothing under row level security, and every daily close was refused
 * with "The selected nozzle_readings.0.nozzle_id is invalid." See SchemaAwarePresenceVerifier.
 *
 * Proof: the schema-named connections are pointed at a database that does not exist. Any rule
 * that still went through them would throw; these pass only if the default connection is used.
 */
test('schema-qualified exists and unique rules use the default connection, written any way', function () {
    $company = Company::create(['name' => 'Schema Rule Co', 'slug' => 'schema-rule-'.str()->lower(str()->random(8)), 'base_currency' => 'PKR']);
    DB::select("SELECT set_config('app.current_company_id', ?, false)", [$company->id]);
    $account = Account::create([
        'company_id' => $company->id, 'code' => '1050', 'name' => 'Cash on Hand',
        'type' => 'asset', 'subtype' => 'cash', 'normal_balance' => 'debit', 'is_active' => true,
    ]);

    $originals = [];
    foreach (['acct', 'fuel', 'inv'] as $name) {
        $originals[$name] = config("database.connections.{$name}");
        config(["database.connections.{$name}.database" => 'db_that_does_not_exist_'.str()->random(8)]);
        DB::purge($name);
    }

    try {
        $rules = [
            'object' => ['required', Rule::exists('acct.accounts', 'id')->where('company_id', $company->id)],
            'string' => 'required|exists:acct.accounts,id',
            'looped' => ['required', Rule::exists(...['acct.accounts', 'id'])],
            'unique_code' => ['required', Rule::unique('acct.accounts', 'code')->where('company_id', $company->id)],
        ];

        $ok = Validator::make(['object' => $account->id, 'string' => $account->id, 'looped' => $account->id, 'unique_code' => '9999'], $rules);
        expect($ok->fails())->toBeFalse();

        $missing = (string) str()->uuid();
        $bad = Validator::make(['object' => $missing, 'string' => $missing, 'looped' => $missing, 'unique_code' => '1050'], $rules);
        expect($bad->errors()->keys())->toEqualCanonicalizing(['object', 'string', 'looped', 'unique_code']);

        // A schema the rule names without anything behind it in the database still just fails, cleanly.
        expect(Validator::make(['n' => $missing], ['n' => 'exists:fuel.nozzles,id'])->fails())->toBeTrue();
    } finally {
        foreach ($originals as $name => $config) {
            config(["database.connections.{$name}" => $config]);
            DB::purge($name);
        }
    }
});
