<?php

use App\Modules\Umrah\Commands\CreateImportedGroup;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\MutamerSheetImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/TicketingFixtures.php';

function mutamerPreviewWorkbook(array $rows, ?string $xmlOverride = null): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'mutamer-preview-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/></Types>');
    $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Mutamers" sheetId="1" r:id="r1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="r1" Target="worksheets/sheet1.xml"/></Relationships>');
    $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach ($rows as $number => $row) {
        $xml .= '<row r="'.$number.'">';
        foreach ($row as $index => $value) {
            $xml .= '<c r="'.chr(65 + $index).$number.'" t="inlineStr"><is><t>'.htmlspecialchars((string) $value, ENT_XML1).'</t></is></c>';
        }
        $xml .= '</row>';
    }
    $zip->addFromString('xl/worksheets/sheet1.xml', $xmlOverride ?? $xml.'</sheetData></worksheet>');
    $zip->close();

    return new UploadedFile($path, 'mutamers.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function mutamerPreviewRead(array $rows, ?string $xml = null): array
{
    $file = mutamerPreviewWorkbook($rows, $xml);
    try {
        return app(MutamerSheetImportService::class)->import($file);
    } finally {
        unlink($file->getPathname());
    }
}

test('preview keeps spreadsheet row numbers and reports errors without losing rows', function () {
    $rows = mutamerPreviewRead([
        1 => ['Issued Mutamer list'],
        3 => ['Mutamer Name', 'Passport Number', 'Mutamer Age', 'Nationality'],
        5 => ['محمد Ali', '00123', '35', 'Pakistan'],
        8 => ['', 'P222', 'bad age', 'Unknown country'],
        9 => ['Duplicate', ' 00123 ', '40', 'Pakistan'],
        10 => ['Missing passport', '', '12.5', 'Pakistan'],
    ]);
    expect($rows)->toHaveCount(4)->and($rows[0]['source_row'])->toBe(5)
        ->and($rows[0]['passport_number'])->toBe('00123')->and($rows[0]['full_name'])->toBe('محمد Ali')
        ->and($rows[0]['errors'])->toBe([])->and($rows[1]['source_row'])->toBe(8)
        ->and($rows[1]['errors'])->toHaveCount(3)->and($rows[1]['nationality'])->toBe('Unknown country')
        ->and($rows[2]['errors'])->toContain('Duplicate passport; first appears on row 5.')
        ->and($rows[3]['errors'])->toHaveCount(2);
});

test('preview accepts 500 passengers and rejects 501 rather than truncating', function () {
    $rows = [1 => ['Mutamer Name', 'Passport Number', 'Mutamer Age', 'Nationality']];
    for ($i = 2; $i <= 501; $i++) {
        $rows[$i] = ['Passenger '.$i, 'PASS'.$i, 30, 'Pakistan'];
    }
    expect(mutamerPreviewRead($rows))->toHaveCount(500);
    $rows[502] = ['One too many', 'OVER', 30, 'Pakistan'];
    expect(fn () => mutamerPreviewRead($rows))->toThrow(ValidationException::class);
});

test('preview rejects a compressed workbook exceeding the unpacked size budget', function () {
    expect(fn () => mutamerPreviewRead([], str_repeat(' ', 21 * 1024 * 1024)))->toThrow(ValidationException::class);
});

test('preview rejects malformed xml entities formulas missing headers and empty lists', function (string $case) {
    $headers = [1 => ['Mutamer Name', 'Passport Number', 'Mutamer Age', 'Nationality']];
    $xml = match ($case) {
        'xml' => '<worksheet broken',
        'entity' => '<!DOCTYPE foo [<!ENTITY x SYSTEM "file:///secret">]><foo/>',
        'formula' => '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>',
        default => null,
    };
    expect(fn () => mutamerPreviewRead($case === 'headers' ? [1 => ['Wrong columns']] : $headers, $xml))->toThrow(ValidationException::class);
})->with(['xml', 'entity', 'formula', 'headers', 'empty']);

function mutamerBookingFixture(): object
{
    $f = ticketingCompany(['industry_code' => 'umrah', 'settings' => ['modules' => ['umrah' => true]]]);
    App\Facades\CompanyContext::setContext($f->company);
    app(App\Services\CompanyRbacBootstrapper::class)->bootstrap($f->company);
    DB::table('auth.company_user')->insert(['company_id' => $f->company->id, 'user_id' => $f->user->id, 'role' => 'owner', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    App\Facades\CompanyContext::assignRole($f->user, 'owner');
    $f->agent = Agent::create(['company_id' => $f->company->id, 'agent_number' => 'IMP-A', 'name' => 'Import agent']);
    $f->data = ['idempotency_key' => (string) str()->uuid(), 'agent_id' => $f->agent->id,
        'includes_visa' => false, 'includes_hotel' => true, 'transport_mode' => 'none', 'transport_required' => false,
        'group_number' => 'IMPORT-1', 'passengers' => [['full_name' => 'Import Passenger', 'passport_number' => 'IMP001', 'imported_age' => 30, 'nationality' => 'Pakistan']]];

    return $f;
}

test('group retries return the original booking without duplicate passengers or charges and permit new journeys', function () {
    $f = mutamerBookingFixture();
    $first = Bus::dispatch(new CreateImportedGroup($f->company->id, $f->data, false));
    $journals = DB::table('acct.transactions')->count();
    $second = Bus::dispatch(new CreateImportedGroup($f->company->id, $f->data, false));
    expect($second->id)->toBe($first->id)->and($first->passengers()->count())->toBe(1)
        ->and(VisaGroup::where('company_id', $f->company->id)->count())->toBe(1)
        ->and(DB::table('acct.transactions')->count())->toBe($journals);
    $next = Bus::dispatch(new CreateImportedGroup($f->company->id, [...$f->data, 'idempotency_key' => (string) str()->uuid(), 'group_number' => 'IMPORT-2'], false));
    expect($next->id)->not->toBe($first->id)->and($next->passengers()->first()->passport_number)->toBe('IMP001');
    $first->update(['status' => 'cancelled']);
    expect(fn () => Bus::dispatch(new CreateImportedGroup($f->company->id, $f->data, false)))->toThrow(ValidationException::class);
});

test('create group validates same-group duplicates and accepts a safe HTTP retry', function () {
    $f = mutamerBookingFixture();
    $url = '/'.$f->company->slug.'/umrah/groups';
    $this->actingAs($f->user);
    $bad = [...$f->data, 'passengers' => [$f->data['passengers'][0], [...$f->data['passengers'][0], 'passport_number' => ' imp 001 ']]];
    $this->post($url, $bad)->assertSessionHasErrors('passengers.1.passport_number');
    $this->post($url, $f->data)->assertSessionHasNoErrors()->assertSessionHas('success');
    $this->post($url, $f->data)->assertSessionHasNoErrors()->assertSessionHas('success');
    expect(VisaGroup::where('company_id', $f->company->id)->count())->toBe(1);
});

test('upload only produces a preview and checks permission without writing passengers or accounting', function () {
    $f = mutamerBookingFixture();
    $file = mutamerPreviewWorkbook([1 => ['Mutamer Name', 'Passport Number', 'Mutamer Age', 'Nationality'], 2 => ['Preview only', 'PREVIEW1', 30, 'Pakistan']]);
    try {
        $url = '/'.$f->company->slug.'/umrah/groups/import-mutamers';
        $this->post($url, ['mutamers_file' => $file])->assertRedirect(route('login'));
        $before = [DB::table('umrah.passengers')->count(), DB::table('umrah.visa_groups')->count(), DB::table('acct.transactions')->count()];
        $this->actingAs($f->user)->from('/'.$f->company->slug.'/umrah/groups/create')->post($url, ['mutamers_file' => $file])
            ->assertSessionHasNoErrors()->assertSessionHas('umrah_imported_mutamers.0.source_row', 2)
            ->assertSessionHas('umrah_imported_mutamers.0.full_name', 'Preview only');
        expect([DB::table('umrah.passengers')->count(), DB::table('umrah.visa_groups')->count(), DB::table('acct.transactions')->count()])->toBe($before);
        $outsider = App\Models\User::factory()->withoutTwoFactor()->create();
        DB::table('auth.company_user')->insert(['company_id' => $f->company->id, 'user_id' => $outsider->id, 'role' => 'operations', 'joined_at' => now(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($outsider)->post($url, ['mutamers_file' => $file])->assertForbidden();
    } finally {
        unlink($file->getPathname());
    }
});

test('a booking key cannot be reused for a different purchasing agent', function () {
    $f = mutamerBookingFixture();
    Bus::dispatch(new CreateImportedGroup($f->company->id, $f->data, false));
    $other = Agent::create(['company_id' => $f->company->id, 'agent_number' => 'OTHER', 'name' => 'Other agent']);
    expect(fn () => Bus::dispatch(new CreateImportedGroup($f->company->id, [...$f->data, 'agent_id' => $other->id], false)))->toThrow(ValidationException::class);
});

test('retry of a visa purchase preserves its posted balances and transaction ids', function () {
    $f = mutamerBookingFixture();
    foreach ([['1100', 'Receivable', 'asset', 'accounts_receivable', 'debit'], ['2000', 'Payable', 'liability', 'accounts_payable', 'credit'], ['4100', 'Visa sales', 'revenue', 'revenue', 'credit'], ['5100', 'Visa cost', 'cogs', 'cogs', 'debit']] as [$code,$name,$type,$subtype,$normal]) {
        App\Modules\Accounting\Models\Account::firstOrCreate(['company_id' => $f->company->id, 'code' => $code], ['name' => $name, 'type' => $type, 'subtype' => $subtype, 'normal_balance' => $normal]);
    }
    $vendor = VisaVendor::create(['company_id' => $f->company->id, 'vendor_number' => 'IMP-V', 'name' => 'Import visa supplier', 'service_type' => 'visa_provider', 'adult_retail_amount' => 1000, 'adult_cost_amount' => 700, 'child_retail_amount' => 500, 'child_cost_amount' => 300, 'is_active' => true]);
    $data = [...$f->data, 'includes_visa' => true, 'vendor_id' => $vendor->id];
    $first = Bus::dispatch(new CreateImportedGroup($f->company->id, $data, false));
    expect((float) $first->total_receivable)->toBe(1000.0)->and($first->sale_transaction_id)->not->toBeNull();
    $snapshot = [];
    foreach (['umrah.visa_groups', 'umrah.agents', 'umrah.visa_vendors', 'umrah.passengers', 'acct.transactions'] as $table) {
        $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
    }
    $again = Bus::dispatch(new CreateImportedGroup($f->company->id, $data, false));
    expect($again->id)->toBe($first->id);
    foreach ($snapshot as $table => $value) {
        expect(DB::table($table)->orderBy('id')->get()->toJson())->toBe($value);
    }
});
