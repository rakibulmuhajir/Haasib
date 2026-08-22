<?php

namespace Database\Seeders\Demo;

use App\Models\Company;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\Hotel;
use App\Modules\Umrah\Models\HotelRoomRate;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaService;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Models\VoucherPassenger;
use App\Modules\Umrah\Services\HotelStayPricingCalculator;
use App\Modules\Umrah\Services\UmrahCoreService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Bab-al-Salam Travel — the Umrah-module demo company.
 *
 * The screenshot this exists for is group accounting: an agent brings a group
 * of pilgrims, the agency buys visas from one vendor and the mandatory bus from
 * another, issues a voucher covering the Makkah and Madinah hotel stays, and
 * every leg of that lands in its own revenue and cost account. Groups, payments
 * and vouchers all go through UmrahCoreService — the same code the UI calls.
 *
 * Two things about this data are worth knowing before reading the ledger:
 *
 * 1. Visa pricing is NOT set here. UmrahCoreService::applyServiceDefaults
 *    overrides whatever sale and cost amounts you pass with the visa vendor's
 *    own per-passenger rates, split by age band. To change the money, change
 *    the vendor's rates.
 * 2. Group sale and cost journals are dated the day the seeder runs, not the
 *    travel date — postGroupSale and postGroupCost both hardcode Carbon::today().
 *    Payments, vouchers and overheads carry their real dates. The period totals
 *    are right; a month-by-month revenue chart would not be.
 *
 * Margins are a travel agency's, not a software company's: roughly 15% gross on
 * visa work and hotel bed-nights, before office overheads.
 */
class DemoTravelAgencySeeder extends Seeder
{
    use DemoSupport;

    public const SLUG = 'demo-babalsalam-travel';

    public function run(): void
    {
        $this->purgeDemoCompany(self::SLUG);

        $user = $this->demoUser();

        $company = $this->buildCompany(
            user: $user,
            name: 'Bab-al-Salam Travel',
            slug: self::SLUG,
            industryCode: 'travel',
            currency: 'PKR',
            country: 'PK',
            bankAccounts: [
                ['account_name' => 'Operating Bank Account', 'account_type' => 'bank', 'currency' => 'PKR'],
                ['account_name' => 'Cash on Hand', 'account_type' => 'cash', 'currency' => 'PKR'],
            ],
            tradeName: 'Bab-al-Salam Travel & Tours',
            industry: 'services',
            address: [
                'line1' => 'Ground Floor, Al-Haram Plaza',
                'line2' => 'Blue Area',
                'city' => 'Islamabad',
                'state' => 'Islamabad Capital Territory',
                'postal_code' => '44000',
                'country' => 'Pakistan',
            ],
            contact: [
                'contact_email' => 'hisab@babalsalam.pk',
                'contact_phone' => '+92 51 227 4400',
            ],
            taxNumber: '4419077-2',
        );

        $this->command?->info("  company: {$company->name} ({$company->id})");

        // Accounts the `travel` pack ships. 4120 Hotel Revenue and 5120 Hotel Cost
        // are the codes UmrahCoreService::accountId() looks for by name of code;
        // without them hotel money silently merges into the visa accounts.
        $bank = $this->accountBySubtype($company, 'bank');
        $cash = $this->accountBySubtype($company, 'cash');
        $this->requireAccount($company, '1100', 'Agent Receivables');
        $this->requireAccount($company, '2100', 'Visa Vendor Payables');
        $this->requireAccount($company, '4100', 'Visa Service Revenue');
        $this->requireAccount($company, '4110', 'Transport Revenue');
        $this->requireAccount($company, '4120', 'Hotel Revenue');
        $this->requireAccount($company, '5100', 'Visa Cost');
        $this->requireAccount($company, '5110', 'Transport Cost');
        $this->requireAccount($company, '5120', 'Hotel Cost');

        $admin = $this->requireAccount($company, '6100', 'General & Administrative');
        $salaries = $this->requireAccount($company, '6200', 'Salaries');
        $rent = $this->requireAccount($company, '6300', 'Office Rent');
        $comms = $this->requireAccount($company, '6400', 'Communication & Courier');

        // The pack ships retained earnings but no capital account; a real agency
        // would add one when the owners put money in.
        $capital = $this->ensureAccount($company, '3000', "Owners' Capital", 'equity', 'equity', 'credit');

        $gl = app(GlPostingService::class);
        $core = app(UmrahCoreService::class);
        $hotelPricing = app(HotelStayPricingCalculator::class);

        $openingDate = Carbon::create(2026, 6, 1);

        // ---- Opening balances ---------------------------------------------------
        $gl->postBalancedTransaction([
            'company_id' => $company->id,
            'transaction_type' => 'journal',
            'date' => $openingDate,
            'currency' => 'PKR',
            'description' => 'Opening balances',
        ], [
            ['account_id' => $bank->id, 'type' => 'debit', 'amount' => 2_400_000, 'description' => 'Opening bank balance'],
            ['account_id' => $cash->id, 'type' => 'debit', 'amount' => 120_000, 'description' => 'Opening office cash'],
            ['account_id' => $capital->id, 'type' => 'credit', 'amount' => 2_520_000, 'description' => 'Capital introduced'],
        ]);

        // ---- Agents -------------------------------------------------------------
        // voucher_cutoff_hours is constrained to 2, 6, 12, 18, 24 or 48.
        $agents = collect([
            ['Al-Noor Travels', 'Karachi', '+92 21 3455 0192', 24, true],
            ['Madina Tours', 'Lahore', '+92 42 3577 4410', 48, true],
            ['Rehmat Travel Service', 'Multan', '+92 61 4512 8830', 12, false],
        ])->map(fn (array $row, int $i) => Agent::create([
            'company_id' => $company->id,
            'agent_number' => sprintf('AGT-%04d', $i + 1),
            'name' => $row[0],
            'city' => $row[1],
            'phone' => $row[2],
            'country' => 'Pakistan',
            'voucher_cutoff_hours' => $row[3],
            'can_create_voucher' => $row[4],
            'can_approve_voucher' => false,
            'can_edit_voucher' => $row[4],
            'is_active' => true,
        ]));

        // ---- Vendors ------------------------------------------------------------
        // Visa and standard-bus rates are independent supplier prices.
        $visaVendor = VisaVendor::create([
            'company_id' => $company->id,
            'vendor_number' => 'VIS-0001',
            'name' => 'Al-Haramain Visa Services',
            'vendor_type' => VisaVendor::TYPE_VISA_PROVIDER,
            'city' => 'Jeddah',
            'email' => 'ops@alharamain-visa.example',
            'adult_retail_amount' => 52_000,
            'adult_cost_amount' => 44_000,
            'child_retail_amount' => 38_000,
            'child_cost_amount' => 32_000,
            'is_default' => true,
            'is_active' => true,
        ]);

        $transportVendor = VisaVendor::create([
            'company_id' => $company->id,
            'vendor_number' => 'TRN-0001',
            'name' => 'Safar Coaches',
            'vendor_type' => VisaVendor::TYPE_TRANSPORT_PROVIDER,
            'city' => 'Makkah',
            'is_company_owned' => false,
            'standard_bus_retail_amount' => 7_500,
            'standard_bus_cost_amount' => 6_000,
            'charge_child_fare' => true,
            'is_active' => true,
        ]);

        // ---- Hotels -------------------------------------------------------------
        // hotels.city is constrained to Makkah or Madinah. Room rates are per bed
        // per night, which is how Umrah accommodation is actually sold.
        $hotels = [];
        foreach ([
            ['Rawaf Makkah Hotels', 'HTV-0001', 'Ajyad Grand — Makkah', 'Makkah', ['quad' => [12_500, 10_800], 'triple' => [15_000, 12_900], 'double' => [21_000, 18_200]]],
            ['Taibah Madinah Hotels', 'HTV-0002', 'Taibah Front — Madinah', 'Madinah', ['quad' => [9_500, 8_100], 'triple' => [11_500, 9_800], 'double' => [16_000, 13_700]]],
        ] as [$vendorName, $vendorNumber, $hotelName, $city, $rates]) {
            $hotelVendor = HotelVendor::create([
                'company_id' => $company->id,
                'vendor_number' => $vendorNumber,
                'name' => $vendorName,
                'city' => $city,
                'is_active' => true,
            ]);

            $hotel = Hotel::create([
                'company_id' => $company->id,
                'hotel_vendor_id' => $hotelVendor->id,
                'name' => $hotelName,
                'city' => $city,
                'is_active' => true,
            ]);

            foreach ($rates as $roomType => [$retail, $cost]) {
                HotelRoomRate::create([
                    'company_id' => $company->id,
                    'hotel_id' => $hotel->id,
                    'room_type' => $roomType,
                    'retail_amount' => $retail,
                    'cost_amount' => $cost,
                    'is_active' => true,
                ]);
            }

            $hotels[$city] = $hotel;
        }

        // ---- Service catalogue --------------------------------------------------
        foreach ([
            ['Umrah Visa — Adult', 52_000, 44_000],
            ['Umrah Visa — Child', 38_000, 32_000],
            ['Ziyarat Transport — Makkah & Madinah', 9_500, 7_200],
        ] as [$name, $retail, $cost]) {
            VisaService::create([
                'company_id' => $company->id,
                'vendor_id' => $visaVendor->id,
                'name' => $name,
                'retail_amount' => $retail,
                'cost_amount' => $cost,
                'is_active' => true,
            ]);
        }

        // ---- Groups, vouchers and payments ---------------------------------------
        $groupSpec = [
            [
                'agent' => 0,
                'name' => 'Ramadan Umrah — Karachi Departure',
                'travel_date' => '2026-06-20',
                'adults' => 10,
                'children' => 2,
                'seat_only' => 0,
                'flight' => ['PK', 'PK-731', 'PK-732', 'Karachi'],
                'rooms' => 3,
                'makkah_nights' => 5,
                'madinah_nights' => 4,
                'settled' => 1.00,
                'pay_visa' => 1.00,
                'pay_hotel' => 1.00,
            ],
            [
                'agent' => 1,
                'name' => 'Shawwal Family Group — Lahore',
                'travel_date' => '2026-07-10',
                'adults' => 8,
                'children' => 0,
                'seat_only' => 3,
                'flight' => ['SV', 'SV-729', 'SV-730', 'Lahore'],
                'rooms' => 2,
                'makkah_nights' => 5,
                'madinah_nights' => 4,
                'settled' => 0.70,
                'pay_visa' => 1.00,
                'pay_hotel' => 1.00,
            ],
            [
                'agent' => 2,
                'name' => 'Summer Umrah — Multan Departure',
                'travel_date' => '2026-07-28',
                'adults' => 13,
                'children' => 2,
                'seat_only' => 0,
                'flight' => ['PF', 'PF-141', 'PF-142', 'Islamabad'],
                'rooms' => 4,
                'makkah_nights' => 6,
                'madinah_nights' => 4,
                'settled' => 0.45,
                'pay_visa' => 0.60,
                'pay_hotel' => 0.50,
            ],
        ];

        $surnames = ['Ahmed', 'Siddiqui', 'Farooq', 'Iqbal', 'Rashid', 'Nawaz', 'Bashir', 'Yousaf', 'Hameed', 'Zafar', 'Sattar', 'Mehmood', 'Kamal', 'Anwar', 'Tariq'];
        $given = ['Muhammad', 'Abdul', 'Fatima', 'Ayesha', 'Bilal', 'Hafsa', 'Usman', 'Zainab', 'Imran', 'Maryam', 'Saad', 'Nadia', 'Kashif', 'Rukhsana', 'Adeel'];

        $voucherNo = 1;
        $groupsCreated = 0;
        $vouchersApproved = 0;

        foreach ($groupSpec as $gi => $spec) {
            $agent = $agents[$spec['agent']];
            $travelDate = Carbon::parse($spec['travel_date']);

            // Passengers. Children are priced from the child band, which the
            // service picks off date_of_birth against the travel date.
            $passengers = [];
            $seat = 0;
            for ($i = 0; $i < $spec['adults']; $i++) {
                $passengers[] = [
                    'full_name' => $given[($gi * 5 + $seat) % count($given)].' '.$surnames[($gi * 7 + $seat) % count($surnames)],
                    'passport_number' => sprintf('%s%07d', chr(65 + ($gi % 26)).chr(75 + ($seat % 10)), 3_100_000 + $gi * 100 + $seat),
                    'date_of_birth' => $travelDate->copy()->subYears(28 + ($seat % 30))->toDateString(),
                    'service_type' => 'visa_transport',
                    'visa_status' => 'approved',
                ];
                $seat++;
            }
            for ($i = 0; $i < $spec['children']; $i++) {
                $passengers[] = [
                    'full_name' => $given[($gi * 3 + $i) % count($given)].' '.$surnames[($gi * 7 + $seat) % count($surnames)],
                    'passport_number' => sprintf('%s%07d', chr(65 + ($gi % 26)).chr(75 + ($seat % 10)), 3_100_000 + $gi * 100 + $seat),
                    'date_of_birth' => $travelDate->copy()->subYears(6 + $i)->toDateString(),
                    'service_type' => 'visa_transport',
                    'visa_status' => 'approved',
                ];
                $seat++;
            }

            // Pilgrims who already hold a visa and buy only a seat on the bus.
            // These are the only passengers that produce Transport Revenue:
            // transportOnlyPassengerCharges sums transport_charge_amount over
            // exactly this service_type, and calculateVisaPricingFromVendor skips
            // them, so they carry no visa sale, no visa cost and no bus deduction.
            for ($i = 0; $i < $spec['seat_only']; $i++) {
                $passengers[] = [
                    'full_name' => $given[($gi * 11 + $i) % count($given)].' '.$surnames[($gi * 5 + $seat) % count($surnames)],
                    'passport_number' => sprintf('%s%07d', chr(65 + ($gi % 26)).chr(75 + ($seat % 10)), 3_100_000 + $gi * 100 + $seat),
                    'date_of_birth' => $travelDate->copy()->subYears(34 + $i)->toDateString(),
                    'service_type' => 'transport_only',
                    'visa_status' => 'delivered',
                    'transport_charge_amount' => 15_000,
                ];
                $seat++;
            }

            $group = $core->createGroup($company->id, [
                'agent_id' => $agent->id,
                'vendor_id' => $visaVendor->id,
                'mandatory_transport_vendor_id' => $transportVendor->id,
                'name' => $spec['name'],
                'travel_date' => $travelDate->toDateString(),
                'transport_required' => true,
                'transport_mode' => VisaGroup::TRANSPORT_STANDARD_BUS,
                'passenger_count' => count($passengers),
                'passengers' => $passengers,
                'flight_airline' => $spec['flight'][0],
                'flight_number' => $spec['flight'][1],
                'hotel_makkah' => $hotels['Makkah']->name,
                'hotel_madinah' => $hotels['Madinah']->name,
            ]);

            $groupsCreated++;

            // ---- Voucher covering both hotel stays ------------------------------
            // Priced through HotelStayPricingCalculator, the same calculator the
            // voucher controller uses; only the snapshot array is assembled here.
            $checkInMakkah = $travelDate->copy()->addDay();
            $checkOutMakkah = $checkInMakkah->copy()->addDays($spec['makkah_nights']);
            $checkOutMadinah = $checkOutMakkah->copy()->addDays($spec['madinah_nights']);

            $stays = [];
            $hotelSale = 0.0;
            $hotelCost = 0.0;
            $costByHotelVendor = [];

            foreach ([
                ['Makkah', $checkInMakkah, $checkOutMakkah],
                ['Madinah', $checkOutMakkah, $checkOutMadinah],
            ] as [$city, $checkIn, $checkOut]) {
                $hotel = $hotels[$city];
                $rate = HotelRoomRate::where('company_id', $company->id)
                    ->where('hotel_id', $hotel->id)
                    ->where('room_type', 'quad')
                    ->firstOrFail();

                $totals = $hotelPricing->calculate(
                    $checkIn->toDateString(),
                    $checkOut->toDateString(),
                    $spec['rooms'],
                    HotelRoomRate::bedsFor($rate->room_type),
                    (float) $rate->retail_amount,
                    (float) $rate->cost_amount,
                );

                $stays[] = [
                    'source' => 'company',
                    'hotel_id' => $hotel->id,
                    'hotel_vendor_id' => $hotel->hotel_vendor_id,
                    'hotel_name' => $hotel->name,
                    'city' => $hotel->city,
                    'room_type' => $rate->room_type,
                    'room_count' => $spec['rooms'],
                    'check_in_date' => $checkIn->toDateString(),
                    'check_out_date' => $checkOut->toDateString(),
                    'unit_retail_amount' => (float) $rate->retail_amount,
                    'unit_cost_amount' => (float) $rate->cost_amount,
                    ...$totals,
                ];

                $hotelSale += $totals['total_retail_amount'];
                $hotelCost += $totals['total_cost_amount'];
                $costByHotelVendor[$hotel->hotel_vendor_id] =
                    ($costByHotelVendor[$hotel->hotel_vendor_id] ?? 0) + $totals['total_cost_amount'];
            }

            $voucher = Voucher::create([
                'company_id' => $company->id,
                'visa_group_id' => $group->id,
                'agent_id' => $group->agent_id,
                'voucher_number' => sprintf('VCH-%04d', $voucherNo++),
                'title' => $spec['name'].' — Travel Voucher',
                'service_bundle' => Voucher::SERVICE_VISA_TRANSPORT_HOTEL,
                'status' => Voucher::STATUS_APPROVED,
                'onward_airline' => $spec['flight'][0],
                'onward_flight_number' => $spec['flight'][1],
                'onward_departure_city' => $spec['flight'][3],
                'onward_arrival_city' => 'Jeddah',
                'onward_departure_at' => $travelDate->copy()->setTime(2, 45),
                'onward_arrival_at' => $travelDate->copy()->setTime(6, 20),
                'return_airline' => $spec['flight'][0],
                'return_flight_number' => $spec['flight'][2],
                'return_departure_city' => 'Madinah',
                'return_arrival_city' => $spec['flight'][3],
                'return_departure_at' => $checkOutMadinah->copy()->setTime(9, 10),
                'return_arrival_at' => $checkOutMadinah->copy()->setTime(15, 35),
                'hotel_stays' => $stays,
                'hotel_sale_amount' => round($hotelSale, 2),
                'hotel_cost_amount' => round($hotelCost, 2),
                'version_number' => 1,
                'created_by_user_id' => $user->id,
            ]);

            foreach ($group->passengers()->pluck('id') as $passengerId) {
                VoucherPassenger::create([
                    'company_id' => $company->id,
                    'voucher_id' => $voucher->id,
                    'visa_group_id' => $group->id,
                    'passenger_id' => $passengerId,
                ]);
            }

            // Posts hotel revenue to 4120 and hotel cost to 5120, and rolls the
            // group and agent balances forward.
            $core->applyVoucherHotelAccounting($voucher, $group->fresh());
            $vouchersApproved++;

            $group = $group->fresh();

            // ---- Agent settles, in instalments -----------------------------------
            $receivable = (float) $group->total_receivable;
            $toCollect = round($receivable * $spec['settled'], 2);

            if ($toCollect > 0) {
                $instalments = $spec['settled'] >= 1.0
                    ? [round($toCollect * 0.4, 2), round($toCollect - round($toCollect * 0.4, 2), 2)]
                    : [$toCollect];

                foreach ($instalments as $n => $amount) {
                    $core->addPayment($company->id, [
                        'agent_id' => $agent->id,
                        'visa_group_id' => $group->id,
                        'direction' => 'received',
                        'payment_date' => $travelDate->copy()->addDays(3 + $n * 12)->toDateString(),
                        'amount' => $amount,
                        'currency' => 'PKR',
                        'method' => $n === 0 ? 'bank_transfer' : 'cash',
                        'account_id' => $n === 0 ? $bank->id : $cash->id,
                        'reference' => sprintf('RCPT-%s-%d', $group->group_number, $n + 1),
                        'payment_number' => null,
                    ]);
                }
            }

            // ---- Agency settles its vendors --------------------------------------
            // visa_group_id matters here. A vendor payment on its own only posts
            // Dr Vendor Advances / Cr Bank; it is the allocation that moves the
            // money against the payable. Omit the group and the advance sits on
            // the balance sheet as an asset forever while the payable never falls.
            $visaCost = round((float) $group->visa_cost_amount * $spec['pay_visa'], 2);
            if ($visaCost > 0) {
                $core->addPayment($company->id, [
                    'direction' => 'sent',
                    'visa_vendor_id' => $visaVendor->id,
                    'visa_group_id' => $group->id,
                    'payment_date' => $travelDate->copy()->subDays(9)->toDateString(),
                    'amount' => $visaCost,
                    'currency' => 'PKR',
                    'method' => 'bank_transfer',
                    'account_id' => $bank->id,
                    'reference' => 'VISA-'.$group->group_number,
                    'payment_number' => null,
                ]);
            }

            $transportCost = (float) $group->transport_cost_amount;
            if ($transportCost > 0) {
                $core->addPayment($company->id, [
                    'direction' => 'sent',
                    'transport_vendor_id' => $transportVendor->id,
                    'visa_group_id' => $group->id,
                    'payment_date' => $travelDate->copy()->addDays(6)->toDateString(),
                    'amount' => $transportCost,
                    'currency' => 'PKR',
                    'method' => 'bank_transfer',
                    'account_id' => $bank->id,
                    'reference' => 'BUS-'.$group->group_number,
                    'payment_number' => null,
                ]);
            }

            // Hotels invoice per property, so each vendor is settled separately
            // against its own share of the voucher's stay costs.
            foreach ($costByHotelVendor as $hotelVendorId => $vendorCost) {
                $amount = round($vendorCost * $spec['pay_hotel'], 2);
                if ($amount <= 0) {
                    continue;
                }

                $core->addPayment($company->id, [
                    'direction' => 'sent',
                    'hotel_vendor_id' => $hotelVendorId,
                    'visa_group_id' => $group->id,
                    'payment_date' => $checkOutMadinah->copy()->addDays(7)->toDateString(),
                    'amount' => $amount,
                    'currency' => 'PKR',
                    'method' => 'bank_transfer',
                    'account_id' => $bank->id,
                    'reference' => 'HTL-'.$group->group_number,
                    'payment_number' => null,
                ]);
            }
        }

        $this->command?->info("  agents: {$agents->count()} groups: {$groupsCreated} vouchers approved: {$vouchersApproved}");

        // ---- An agent sends money ahead of a group -------------------------------
        // Every receipt above names a visa_group_id, and allocatePayment() caps
        // each one at that group's outstanding balance, so nothing was ever left
        // over. That made the whole refund feature unreachable in the demo data:
        // a refund's ceiling is what the agent paid in excess of what they owe,
        // and no agent here had a rupee of excess. This is the ordinary case that
        // creates one -- an agent wires a round sum for a group that has not been
        // priced yet, so it sits as an unallocated advance until there is
        // something to apply it to, or until they ask for it back.
        $advanceAgent = $agents->firstWhere('name', 'Al-Noor Travels');
        if ($advanceAgent) {
            $core->addPayment($company->id, [
                'agent_id' => $advanceAgent->id,
                'direction' => 'received',
                'payment_date' => '2026-08-05',
                'amount' => 250_000,
                'currency' => 'PKR',
                'method' => 'bank_transfer',
                'account_id' => $bank->id,
                'reference' => 'ADV-ALNOOR-2026-08',
                'payment_number' => null,
            ]);
            $this->command?->info('  unallocated agent advance: 250,000 (Al-Noor Travels)');
        }

        // ---- Office overheads ----------------------------------------------------
        // Sized against roughly 800,000 of gross profit across the period, so the
        // agency lands on a believable single-digit net margin.
        $overheadMonths = 0;
        foreach ([Carbon::create(2026, 6, 30), Carbon::create(2026, 7, 31)] as $monthEnd) {
            $gl->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_type' => 'journal',
                'date' => $monthEnd,
                'currency' => 'PKR',
                'description' => 'Office overheads — '.$monthEnd->format('F Y'),
            ], [
                ['account_id' => $rent->id, 'type' => 'debit', 'amount' => 60_000, 'description' => 'Office rent'],
                ['account_id' => $salaries->id, 'type' => 'debit', 'amount' => 150_000, 'description' => 'Counter and operations staff'],
                ['account_id' => $comms->id, 'type' => 'debit', 'amount' => 12_000, 'description' => 'Phone, internet and courier'],
                ['account_id' => $admin->id, 'type' => 'debit', 'amount' => 20_000, 'description' => 'Utilities and office supplies'],
                ['account_id' => $bank->id, 'type' => 'credit', 'amount' => 242_000],
            ]);
            $overheadMonths++;
        }

        $this->command?->info("  overhead months posted: {$overheadMonths}");

        $this->syncBankBalances($company);
    }
}
