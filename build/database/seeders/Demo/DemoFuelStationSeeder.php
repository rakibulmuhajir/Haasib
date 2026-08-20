<?php

namespace Database\Seeders\Demo;

use App\Models\Company;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\BillLineItem;
use App\Modules\Accounting\Models\Vendor;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Actions\Product\SetupAction;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Services\DailyCloseService;
use App\Modules\FuelStation\Services\VendorCardSettlementService;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\Warehouse;
use App\Modules\Inventory\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Crescent Fuel Station — the fuel-module demo company.
 *
 * The screenshot this exists for is the daily close: nozzle meter readings,
 * tank dips with a shrinkage variance, split cash/card/fleet-card takings,
 * and the journal that falls out of it. Nothing in here is written straight
 * into the tables — the station is built by the real product-setup action and
 * every trading day is posted by DailyCloseService, the same code the UI calls.
 *
 * Margins are deliberately thin. A dealer in Pakistan earns an OGRA-set
 * commission of roughly PKR 7.50 a litre on petrol and diesel — not a
 * percentage — so the station runs on about a 2.5% gross and 1.5% net margin.
 * Fuel retail is a volume business and a demo showing 30% margins would be a
 * lie. Hi-octane is deregulated and carries a wider spread.
 *
 * Pump prices are held flat across the 30 days. OGRA revises fortnightly, but
 * modelling a mid-period revision without also passing it through to the pump
 * would quietly distort the margin, which is the one number here worth trusting.
 */
class DemoFuelStationSeeder extends Seeder
{
    use DemoSupport;

    public const SLUG = 'demo-crescent-fuel';

    /** Trading days of history to post. */
    private const DAYS = 30;

    public function run(): void
    {
        $this->purgeDemoCompany(self::SLUG);

        $user = $this->demoUser();

        $company = $this->buildCompany(
            user: $user,
            name: 'Crescent Fuel Station',
            slug: self::SLUG,
            industryCode: 'fuel_station',
            currency: 'PKR',
            country: 'PK',
            bankAccounts: [
                ['account_name' => 'Operating Bank Account', 'account_type' => 'bank', 'currency' => 'PKR'],
                ['account_name' => 'Cash on Hand', 'account_type' => 'cash', 'currency' => 'PKR'],
            ],
            tradeName: 'Crescent Filling Station',
            industry: 'energy',
            address: [
                'line1' => 'Plot 14, Main Multan Road',
                'line2' => 'Thokar Niaz Baig',
                'city' => 'Lahore',
                'state' => 'Punjab',
                'postal_code' => '53700',
                'country' => 'Pakistan',
            ],
            contact: [
                'contact_email' => 'accounts@crescentfuel.pk',
                'contact_phone' => '+92 42 3541 2200',
            ],
            taxNumber: '3520124-8',
        );

        $this->command?->info("  company: {$company->name} ({$company->id})");

        // The fuel_station chart-of-accounts pack ships per-product revenue, COGS,
        // inventory and shrinkage accounts, plus the clearing accounts the daily
        // close needs. Resolve its codes; don't invent any.
        $bank = $this->accountBySubtype($company, 'bank');
        $cash = $this->accountBySubtype($company, 'cash');
        $ap = $this->requireAccount($company, '2100', 'Accounts Payable - Fuel Suppliers');
        $partnerCapital = $this->requireAccount($company, '3000', 'Partner Capital');
        $fuelCardClearing = $this->requireAccount($company, '1080', 'Parco Card Clearing');
        $cardClearing = $this->requireAccount($company, '1090', 'Card Receipts Clearing');
        $petrolInventory = $this->requireAccount($company, '1200', 'Fuel Inventory - Petrol');
        $hiOctaneInventory = $this->requireAccount($company, '1201', 'Fuel Inventory - Hi-Octane');
        $dieselInventory = $this->requireAccount($company, '1202', 'Fuel Inventory - Diesel');

        $electricity = $this->requireAccount($company, '6110', 'Electricity');
        $foodTea = $this->requireAccount($company, '6130', 'Food & Tea');
        $salaries = $this->requireAccount($company, '6150', 'Salaries & Wages');
        $rent = $this->requireAccount($company, '6190', 'Rent');

        $startDate = Carbon::today()->subDays(self::DAYS);

        // ---- Station configuration -------------------------------------------
        // Explicit account wiring. resolveAccounts() falls back to guessing by
        // code when these are null; the demo should not depend on that.
        StationSettings::updateOrCreate(['company_id' => $company->id], [
            'fuel_vendor' => 'parco',
            'has_partners' => true,
            'has_amanat' => false,
            'has_lubricant_sales' => false,
            'has_investors' => false,
            'dual_meter_readings' => false,
            'track_attendant_handovers' => false,
            'payment_channels' => [
                ['code' => 'cash', 'label' => 'Cash', 'type' => 'cash'],
                ['code' => 'card_pos', 'label' => 'Card (POS)', 'type' => 'card_pos', 'clearing_account_id' => $cardClearing->id],
                ['code' => 'parco_card', 'label' => 'PARCO Fleet Card', 'type' => 'fuel_card', 'clearing_account_id' => $fuelCardClearing->id],
            ],
            'cash_account_id' => $cash->id,
            'operating_bank_account_id' => $bank->id,
            'card_pos_clearing_account_id' => $cardClearing->id,
            'fuel_card_clearing_account_id' => $fuelCardClearing->id,
            'fuel_sales_account_id' => $this->requireAccount($company, '4100', 'Fuel Sales - Petrol')->id,
            'fuel_cogs_account_id' => $this->requireAccount($company, '5100', 'Cost of Fuel - Petrol')->id,
            'fuel_inventory_account_id' => $petrolInventory->id,
            'cash_over_short_account_id' => $this->requireAccount($company, '6180', 'Cash Short/Over')->id,
            'partner_drawings_account_id' => $this->requireAccount($company, '3200', 'Partner Drawings')->id,
            'employee_advances_account_id' => $this->requireAccount($company, '1150', 'Employee Advances')->id,
        ]);

        // ---- Products, tanks, pumps and nozzles -------------------------------
        // Driven through the real product-setup action, so items get their
        // per-product GL mappings, opening dips and initial OGRA rates.
        $setup = $this->asCompany($company, $user, fn () => app(SetupAction::class)->handle([
            'effective_date' => $startDate->toDateString(),
            'products' => [
                [
                    'type' => 'fuel',
                    'name' => 'Petrol',
                    'fuel_category' => 'petrol',
                    'purchase_rate' => 290.00,
                    'sale_rate' => 297.50,
                    'opening_quantity' => 20_000,
                    'new_tank' => ['name' => 'Tank 1 — Petrol', 'code' => 'TNK-PET', 'capacity' => 30_000, 'low_level_alert' => 4_000],
                    'pump_setups' => [
                        ['name' => 'Point 1', 'nozzle_count' => 2, 'nozzles' => [
                            ['code' => '1A', 'label' => 'Petrol 1A', 'opening_electronic' => 412_500],
                            ['code' => '1B', 'label' => 'Petrol 1B', 'opening_electronic' => 388_140],
                        ]],
                        ['name' => 'Point 2', 'nozzle_count' => 2, 'nozzles' => [
                            ['code' => '2A', 'label' => 'Petrol 2A', 'opening_electronic' => 296_870],
                            ['code' => '2B', 'label' => 'Petrol 2B', 'opening_electronic' => 274_320],
                        ]],
                    ],
                ],
                [
                    'type' => 'fuel',
                    'name' => 'Diesel',
                    'fuel_category' => 'diesel',
                    'purchase_rate' => 296.40,
                    'sale_rate' => 304.10,
                    'opening_quantity' => 12_000,
                    'new_tank' => ['name' => 'Tank 2 — Diesel', 'code' => 'TNK-DSL', 'capacity' => 25_000, 'low_level_alert' => 3_500],
                    'pump_setups' => [
                        ['name' => 'Point 3', 'nozzle_count' => 2, 'nozzles' => [
                            ['code' => '3A', 'label' => 'Diesel 3A', 'opening_electronic' => 531_260],
                            ['code' => '3B', 'label' => 'Diesel 3B', 'opening_electronic' => 498_710],
                        ]],
                    ],
                ],
                [
                    'type' => 'fuel',
                    'name' => 'Hi-Octane',
                    'fuel_category' => 'high_octane',
                    'purchase_rate' => 322.00,
                    'sale_rate' => 334.50,
                    'opening_quantity' => 4_000,
                    'new_tank' => ['name' => 'Tank 3 — Hi-Octane', 'code' => 'TNK-HOC', 'capacity' => 10_000, 'low_level_alert' => 1_500],
                    'pump_setups' => [
                        ['name' => 'Point 4', 'nozzle_count' => 1, 'nozzles' => [
                            ['code' => '4A', 'label' => 'Hi-Octane 4A', 'opening_electronic' => 92_480],
                        ]],
                    ],
                ],
            ],
        ]));

        $this->command?->info(
            "  tanks: {$setup['data']['tanks_created']}"
            ." pumps: {$setup['data']['pumps_created']}"
            ." nozzles: {$setup['data']['nozzles_created']}"
        );

        $items = Item::where('company_id', $company->id)
            ->whereNotNull('fuel_category')
            ->get()
            ->keyBy('fuel_category');

        $tanks = Warehouse::where('company_id', $company->id)
            ->where('warehouse_type', 'tank')
            ->get()
            ->keyBy('linked_item_id');

        $nozzles = Nozzle::where('company_id', $company->id)
            ->orderBy('sort_order')->orderBy('code')
            ->get();

        $gl = app(GlPostingService::class);

        // ---- Opening balances -------------------------------------------------
        // Product setup records the physical opening dip but posts no GL for it,
        // so the value of that fuel is brought on to the books here alongside the
        // partners' cash. One balanced entry, dated the day before trading starts.
        $openingStockValue = [
            'petrol' => 20_000 * 290.00,
            'diesel' => 12_000 * 296.40,
            'high_octane' => 4_000 * 322.00,
        ];
        $openingCashFloat = 60_000.0;
        $openingBankBalance = 1_850_000.0;
        $totalOpeningStock = array_sum($openingStockValue);

        $gl->postBalancedTransaction([
            'company_id' => $company->id,
            'transaction_type' => 'journal',
            'date' => $startDate->copy()->subDay(),
            'currency' => 'PKR',
            'description' => 'Opening balances',
        ], [
            ['account_id' => $bank->id, 'type' => 'debit', 'amount' => $openingBankBalance, 'description' => 'Opening bank balance'],
            ['account_id' => $cash->id, 'type' => 'debit', 'amount' => $openingCashFloat, 'description' => 'Opening cash float'],
            ['account_id' => $petrolInventory->id, 'type' => 'debit', 'amount' => $openingStockValue['petrol'], 'description' => '20,000 L petrol in Tank 1'],
            ['account_id' => $dieselInventory->id, 'type' => 'debit', 'amount' => $openingStockValue['diesel'], 'description' => '12,000 L diesel in Tank 2'],
            ['account_id' => $hiOctaneInventory->id, 'type' => 'debit', 'amount' => $openingStockValue['high_octane'], 'description' => '4,000 L hi-octane in Tank 3'],
            ['account_id' => $partnerCapital->id, 'type' => 'credit', 'amount' => $openingBankBalance + $openingCashFloat + $totalOpeningStock, 'description' => 'Partner capital introduced'],
        ]);

        // ---- Supplier -----------------------------------------------------------
        $depot = Vendor::create([
            'company_id' => $company->id,
            'vendor_number' => 'VEND-0001',
            'name' => 'PARCO Depot — Mehmood Kot',
            'email' => 'dispatch@parco-depot.example',
            'vendor_type' => 'fuel_refinery',
            'address' => 'Mehmood Kot, Muzaffargarh, Pakistan',
            'base_currency' => 'PKR',
            'payment_terms' => 7,
            'ap_account_id' => $ap->id,
            'is_active' => true,
        ]);

        // ---- Tanker deliveries ---------------------------------------------------
        // Bill the depot and receive into the tank. postBill puts the value into
        // fuel inventory against AP; receiveLineItem moves the litres and rolls
        // the weighted-average cost the daily close reads back out as COGS.
        $inventory = app(InventoryService::class);
        $deliveries = [
            [6, 'petrol', 12_000, 290.00],
            [11, 'diesel', 12_000, 296.40],
            [18, 'petrol', 12_000, 290.00],
        ];

        $billNo = 1;
        foreach ($deliveries as [$dayOffset, $category, $litres, $rate]) {
            $date = $startDate->copy()->addDays($dayOffset);
            $item = $items[$category];
            $amount = round($litres * $rate, 2);

            $bill = Bill::create([
                'company_id' => $company->id,
                'vendor_id' => $depot->id,
                'bill_number' => sprintf('BILL-%s-%04d', $date->format('Y'), $billNo),
                'vendor_invoice_number' => sprintf('PARCO-%06d', 400_000 + $billNo),
                'bill_date' => $date,
                'due_date' => $date->copy()->addDays(7),
                'status' => 'paid',
                'currency' => 'PKR',
                'base_currency' => 'PKR',
                'exchange_rate' => 1,
                'subtotal' => $amount,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => $amount,
                'paid_amount' => $amount,
                'balance' => 0,
                'base_amount' => $amount,
                'payment_terms' => 7,
                'received_at' => $date,
                'approved_at' => $date,
                'paid_at' => $date->copy()->addDays(7),
            ]);

            $line = BillLineItem::create([
                'company_id' => $company->id,
                'bill_id' => $bill->id,
                'line_number' => 1,
                'description' => number_format($litres).' L '.$item->name.' — tanker delivery',
                'quantity' => $litres,
                'unit_price' => $rate,
                'tax_rate' => 0,
                'discount_rate' => 0,
                'line_total' => $amount,
                'tax_amount' => 0,
                'total' => $amount,
                'item_id' => $item->id,
                'warehouse_id' => $tanks[$item->id]->id,
                'quantity_received' => $litres,
                'expense_account_id' => $item->asset_account_id,
                'account_id' => $item->asset_account_id,
            ]);

            $gl->postBill($bill->fresh(['lineItems', 'vendor', 'company']));
            $inventory->receiveLineItem($bill, $line, (float) $litres, $tanks[$item->id]->id, $date->toDateString());

            // Settled by bank transfer on terms.
            $payDate = $date->copy()->addDays(7);
            if ($payDate->isFuture()) {
                $payDate = Carbon::yesterday();
            }
            $gl->postBalancedTransaction([
                'company_id' => $company->id,
                'transaction_type' => 'payment',
                'date' => $payDate,
                'currency' => 'PKR',
                'description' => "Payment to {$depot->name} — {$bill->bill_number}",
            ], [
                ['account_id' => $ap->id, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $bank->id, 'type' => 'credit', 'amount' => $amount],
            ]);

            $billNo++;
        }

        $this->command?->info('  tanker deliveries: '.count($deliveries));

        // ---- Thirty trading days ------------------------------------------------
        $closeService = app(DailyCloseService::class);
        $rand = $this->seededRandom(20260809);

        // Meter and dip state carried forward between days, exactly as a real
        // station does: today's opening reading is yesterday's closing.
        $meters = [];
        foreach ($nozzles as $nozzle) {
            $meters[$nozzle->id] = (float) $nozzle->last_closing_reading;
        }
        $dips = [
            $tanks[$items['petrol']->id]->id => 20_000.0,
            $tanks[$items['diesel']->id]->id => 12_000.0,
            $tanks[$items['high_octane']->id]->id => 4_000.0,
        ];
        $deliveryByDay = [];
        foreach ($deliveries as [$dayOffset, $category, $litres, $rate]) {
            $deliveryByDay[$dayOffset][$tanks[$items[$category]->id]->id] = $litres;
        }

        // Typical litres per nozzle per day, by fuel.
        $baseVolume = ['petrol' => 300.0, 'diesel' => 350.0, 'high_octane' => 60.0];
        $saleRate = ['petrol' => 297.50, 'diesel' => 304.10, 'high_octane' => 334.50];

        $cashInDrawer = $openingCashFloat;
        $closesPosted = 0;

        // Card and fleet-card takings per day, settled into the bank after the loop.
        $pendingCard = [];
        $pendingFleet = [];

        for ($day = 0; $day < self::DAYS; $day++) {
            $date = $startDate->copy()->addDays($day);
            if ($date->isFuture() || $date->isToday()) {
                break;
            }

            // Weekends are busier at the pump; Fridays quieter around prayers.
            $dayFactor = match ($date->dayOfWeek) {
                Carbon::SATURDAY, Carbon::SUNDAY => 1.18,
                Carbon::FRIDAY => 0.88,
                default => 1.0,
            };

            $nozzleReadings = [];
            $litresByTank = [];
            $revenue = 0.0;

            foreach ($nozzles as $nozzle) {
                $item = $items->firstWhere('id', $nozzle->item_id);
                $category = $item->fuel_category;

                $litres = round($baseVolume[$category] * $dayFactor * $rand(0.82, 1.20), 2);
                $opening = $meters[$nozzle->id];
                $closing = round($opening + $litres, 2);
                $meters[$nozzle->id] = $closing;

                $nozzleReadings[] = [
                    'nozzle_id' => $nozzle->id,
                    'item_id' => $nozzle->item_id,
                    'opening_electronic' => $opening,
                    'closing_electronic' => $closing,
                    'liters_sold' => $litres,
                    'sale_rate' => $saleRate[$category],
                ];

                $litresByTank[$nozzle->tank_id] = ($litresByTank[$nozzle->tank_id] ?? 0) + $litres;
                $revenue += $litres * $saleRate[$category];
            }

            // Tank dips. Expected = opening + deliveries - sales, less evaporation
            // and handling loss, which is what makes the variance column non-trivial.
            //
            // 0.15–0.30% of throughput. An earlier version used a tenth of that and
            // the whole month came to 23 litres of petrol: DailyCloseService treats
            // anything inside ±0.5 L as 'none' and posts no journal, so the variance
            // column read as float noise and two of the three tanks never showed a
            // loss at all. Real evaporation is well above the tolerance.
            $tankReadings = [];
            foreach ($dips as $tankId => $openingDip) {
                $received = $deliveryByDay[$day][$tankId] ?? 0;
                $sold = $litresByTank[$tankId] ?? 0;
                $shrinkage = round($sold * $rand(0.0015, 0.0030), 2);
                $closingDip = round($openingDip + $received - $sold - $shrinkage, 2);
                $dips[$tankId] = $closingDip;

                $tankReadings[] = [
                    'tank_id' => $tankId,
                    'stick_reading' => round($closingDip / 40, 1),
                    'liters' => $closingDip,
                ];
            }

            // Takings split. Roughly a tenth on card, a twelfth on fleet cards.
            $cardTake = round($revenue * $rand(0.08, 0.13), 2);
            $fleetTake = round($revenue * $rand(0.05, 0.10), 2);
            $paymentReceipts = [
                'card_pos' => ['entries' => [['reference' => 'POS batch '.$date->format('dm'), 'amount' => $cardTake]]],
                'parco_card' => ['entries' => [['reference' => 'Fleet batch '.$date->format('dm'), 'amount' => $fleetTake]]],
            ];

            // Cash expenses paid out of the drawer.
            $expenses = [
                ['account_id' => $foodTea->id, 'description' => 'Staff tea and meals', 'amount' => 800],
            ];
            if ($date->day === 5) {
                $expenses[] = ['account_id' => $electricity->id, 'description' => 'Electricity bill — '.$date->copy()->subMonth()->format('F'), 'amount' => 28_000];
            }
            if ($date->day === 1) {
                $expenses[] = ['account_id' => $rent->id, 'description' => 'Station ground rent — '.$date->format('F Y'), 'amount' => 35_000];
            }
            if ($date->day === 28) {
                $expenses[] = ['account_id' => $salaries->id, 'description' => 'Attendant and manager salaries — '.$date->format('F Y'), 'amount' => 90_000];
            }
            $expenseTotal = array_sum(array_column($expenses, 'amount'));

            // Bank the day's takings, keeping a working float in the drawer.
            $cashFromSales = $revenue - $cardTake - $fleetTake;
            $availableToBank = $cashInDrawer + $cashFromSales - $expenseTotal - 60_000;
            $bankDeposit = $availableToBank > 0 ? floor($availableToBank / 5_000) * 5_000 : 0;

            $bankDeposits = $bankDeposit > 0
                ? [['bank_account_id' => $bank->id, 'amount' => $bankDeposit, 'reference' => 'DEP-'.$date->format('Ymd'), 'purpose' => 'Daily takings']]
                : [];

            // What the drawer should hold, plus the small miscount a real day has.
            $expectedClosing = $cashInDrawer + $cashFromSales - $expenseTotal - $bankDeposit;
            $variance = round($rand(-260, 190), 0);
            $closingCash = round(max(0, $expectedClosing + $variance), 2);

            $closeService->processDailyClose($company->id, [
                'date' => $date->toDateString(),
                'nozzle_readings' => $nozzleReadings,
                'tank_readings' => $tankReadings,
                'opening_cash' => round($cashInDrawer, 2),
                'payment_receipts' => $paymentReceipts,
                'bank_deposits' => $bankDeposits,
                'expenses' => $expenses,
                'closing_cash' => $closingCash,
                'notes' => 'Day closed by station manager.',
            ], $user);

            $cashInDrawer = $closingCash;
            $pendingCard[$date->toDateString()] = $cardTake;
            $pendingFleet[$date->toDateString()] = $fleetTake;
            $closesPosted++;
        }

        $this->command?->info("  daily closes posted: {$closesPosted}");

        // ---- Payment-channel settlements ---------------------------------------
        // Without these the clearing accounts only ever get debited and the
        // balance sheet shows a month of card takings that never reached the
        // bank. The acquirer settles card batches every few days net of its
        // discount; PARCO reimburses fleet-card sales fortnightly at face value.
        $settlements = app(VendorCardSettlementService::class);

        $settleBatches = function (array $pending, int $everyDays, int $lagDays, float $feeRate, string $clearingAccountId, string $label) use ($company, $settlements): int {
            $posted = 0;
            $batch = [];
            $dates = array_keys($pending);

            foreach ($dates as $i => $day) {
                $batch[$day] = $pending[$day];

                $isLast = $i === count($dates) - 1;
                if (count($batch) < $everyDays && ! $isLast) {
                    continue;
                }

                $settlementDate = Carbon::parse(array_key_last($batch))->addDays($lagDays);

                // A batch whose settlement date hasn't arrived yet is genuinely
                // still in transit — leave it sitting in the clearing account,
                // which is what the balance sheet should show.
                if ($settlementDate->isFuture()) {
                    break;
                }

                $gross = round(array_sum($batch), 2);
                $fees = round($gross * $feeRate, 2);

                $settlements->settleClearingAccount($company->id, [
                    'settlement_date' => $settlementDate->toDateString(),
                    'reference' => $label.'-'.$settlementDate->format('Ymd'),
                    'clearing_account_id' => $clearingAccountId,
                    'amount_received' => round($gross - $fees, 2),
                    'fees' => $fees,
                    'notes' => count($batch).' day(s) of '.$label.' takings',
                ]);

                $batch = [];
                $posted++;
            }

            return $posted;
        };

        $cardSettlements = $settleBatches($pendingCard, 3, 2, 0.010, $cardClearing->id, 'POS');
        $fleetSettlements = $settleBatches($pendingFleet, 14, 3, 0.0, $fuelCardClearing->id, 'FLEET');

        $this->command?->info("  settlements: {$cardSettlements} card, {$fleetSettlements} fleet");

        $this->syncBankBalances($company);
    }
}
