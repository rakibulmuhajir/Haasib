<?php

// Read-only visual QA: synthetic in-memory records, no database writes.
require __DIR__.'/../../build/vendor/autoload.php';
$app = require __DIR__.'/../../build/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$company = new App\Models\Company(['name' => 'Sample Travel Company', 'settings' => []]);
$agent = new App\Modules\Umrah\Models\Agent;
$agent->setRelation('customer', new App\Modules\Accounting\Models\Customer(['name' => 'Sample Booking Agent']));
$visa = new App\Modules\Umrah\Models\VisaVendor;
$visa->setRelation('vendor', new App\Modules\Accounting\Models\Vendor(['name' => 'Sample Visa Provider']));
$transport = new App\Modules\Umrah\Models\VisaVendor;
$transport->setRelation('vendor', new App\Modules\Accounting\Models\Vendor(['name' => 'Sample Transport Provider']));
$group = new App\Modules\Umrah\Models\VisaGroup(['group_number' => 'QA-ONLY', 'transport_mode' => 'standard_bus']);
$group->setRelation('vendor', $visa)->setRelation('mandatoryTransportVendor', $transport)->setRelation('transportItems', collect());
$voucher = new App\Modules\Umrah\Models\Voucher([
    'voucher_number' => 'QA-PRINT-008', 'title' => 'Synthetic sample - not valid for travel', 'status' => 'draft', 'service_bundle' => 'visa_transport_hotel',
    'created_at' => '2026-09-07', 'version_number' => 1,
    'onward_airline' => 'SV', 'onward_flight_number' => '701', 'onward_departure_city' => 'KHI', 'onward_arrival_city' => 'JED',
    'onward_departure_at' => '2026-10-01 10:00', 'onward_arrival_at' => '2026-10-01 13:00',
    'return_airline' => 'SV', 'return_flight_number' => '700', 'return_departure_city' => 'JED', 'return_arrival_city' => 'KHI',
    'return_departure_at' => '2026-10-15 15:00', 'return_arrival_at' => '2026-10-15 20:00',
    'hotel_stays' => array_map(fn ($index) => [
        'city' => $index === 1 ? 'Madinah' : 'Makkah', 'hotel_name' => 'Sample Hotel '.($index + 1), 'room_type' => 'quad', 'room_count' => 2,
        'meal_plan' => 'Breakfast', 'check_in_date' => ['2026-10-01', '2026-10-06', '2026-10-11'][$index],
        'check_out_date' => ['2026-10-06', '2026-10-11', '2026-10-15'][$index], 'night_count' => $index === 2 ? 4 : 5,
        'map_url' => 'https://www.google.com/maps/',
    ], [0, 1, 2]),
    'print_details' => ['footer_text' => "Please contact the representative responsible for your city before travelling.\nSample terms for layout testing only.", 'contacts' => array_map(fn ($city) => [
        'name' => $city.' Representative', 'responsibility' => $city === 'Jeddah' ? 'Airport / transport' : 'Local assistance', 'city' => $city,
        'organization' => 'Sample Support Company', 'phone' => '+966 500 000 000', 'whatsapp' => '',
    ], ['Makkah', 'Madinah', 'Jeddah'])],
]);
$voucher->setRelation('agent', $agent)->setRelation('group', $group)->setRelation('passengers', collect(range(1, 8))->map(fn ($index) => new App\Modules\Umrah\Models\Passenger([
    'full_name' => 'Sample Passenger '.$index, 'passport_number' => 'SAMPLE00'.$index, 'nationality' => 'Pakistan', 'date_of_birth' => '1990-01-01', 'visa_status' => 'approved',
])));
$data = ['company' => $company, 'voucher' => $voucher, 'letterhead' => ['lines' => ['Sample company address - visual QA only']], ...app(App\Modules\Umrah\Services\VoucherPrintDocument::class)->payload($company, $voucher)];
Barryvdh\DomPDF\Facade\Pdf::loadView('umrah::vouchers.pdf', $data)->setOption('defaultMediaType', 'print')->setPaper('a4')->save(__DIR__.'/../../tmp/pdfs/voucher-print-preview.pdf');
echo "Created tmp/pdfs/voucher-print-preview.pdf\n";
