<?php

namespace App\Modules\FuelStation\Services;

use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\User;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\GlPostingService;
use App\Modules\FuelStation\Models\Nozzle;
use App\Modules\FuelStation\Models\NozzleReading;
use App\Modules\FuelStation\Models\RateChange;
use App\Modules\FuelStation\Models\StationSettings;
use App\Modules\FuelStation\Models\TankReading;
use App\Modules\Inventory\Models\Item;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\SalaryAdvance;
use Illuminate\Support\Facades\DB;

class DailyCloseService
{
    public function __construct(
        private readonly GlPostingService $postingService,
    ) {}

    /**
     * Get the previous day's closing cash balance.
     */
    public function getPreviousDayClosing(string $companyId, string $date): array
    {
        $previousDate = date('Y-m-d', strtotime($date . ' -1 day'));

        // Look for the most recent daily close transaction before this date
        $previousClose = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->where('transaction_date', '<', $date)
            ->whereNull('deleted_at')
            ->orderByDesc('transaction_date')
            ->first();

        if (!$previousClose) {
            return [
                'date' => null,
                'closing_cash' => 0,
                'exists' => false,
            ];
        }

        // Get the closing cash from metadata or calculate from entries
        $metadata = $previousClose->metadata ?? [];
        if (!is_array($metadata)) {
            $metadata = [];
        }

        return [
            'date' => $previousClose->transaction_date->toDateString(),
            'closing_cash' => (float) ($metadata['closing_cash'] ?? 0),
            'exists' => true,
        ];
    }

    /**
     * Process the complete daily close.
     *
     * @param string $companyId
     * @param array $data
     * @param User $user
     * @param bool $isCorrection Whether this is a correction entry (allows multiple per date)
     */
    public function processDailyClose(string $companyId, array $data, User $user, bool $isCorrection = false): array
    {
        return DB::transaction(function () use ($companyId, $data, $user, $isCorrection) {
            $date = $data['date'];
            $transactionNumber = $this->generateTransactionNumber($companyId, $date, $isCorrection);

            // Resolve all required accounts
            $accounts = $this->resolveAccounts($companyId);

            $currency = 'PKR'; // Default for Pakistan

            $entries = [];
            $metadata = [
                'date' => $date,
                'opening_cash' => $data['opening_cash'],
                'closing_cash' => $data['closing_cash'],
            ];

            // ─────────────────────────────────────────────────────────────────
            // 1. Process Fuel Sales (from nozzle readings)
            // ─────────────────────────────────────────────────────────────────
            $totalRevenue = 0;
            $totalCogs = 0;
            $salesByFuel = [];
            $nozzleReadingsData = [];

            foreach ($data['nozzle_readings'] as $reading) {
                $liters = (float) $reading['liters_sold'];

                // Get the item for cost calculation
                $item = Item::where('id', $reading['item_id'])
                    ->where('company_id', $companyId)
                    ->first();

                $saleRate = (float) $reading['sale_rate'];
                $avgCost = (float) ($item?->avg_cost ?? 0);

                $revenue = round($liters * $saleRate, 2);
                $cogs = round($liters * $avgCost, 2);

                if ($liters > 0) {
                    $totalRevenue += $revenue;
                    $totalCogs += $cogs;

                    $fuelCategory = $item?->fuel_category ?? 'unknown';
                    if (!isset($salesByFuel[$fuelCategory])) {
                        $salesByFuel[$fuelCategory] = ['liters' => 0, 'revenue' => 0, 'cogs' => 0];
                    }
                    $salesByFuel[$fuelCategory]['liters'] += $liters;
                    $salesByFuel[$fuelCategory]['revenue'] += $revenue;
                    $salesByFuel[$fuelCategory]['cogs'] += $cogs;
                }

                // Store nozzle reading data for later save
                $nozzleReadingsData[] = [
                    'nozzle_id' => $reading['nozzle_id'],
                    'item_id' => $reading['item_id'],
                    'opening_electronic' => (float) $reading['opening_electronic'],
                    'closing_electronic' => (float) $reading['closing_electronic'],
                    'opening_manual' => isset($reading['opening_manual']) ? (float) $reading['opening_manual'] : null,
                    'closing_manual' => isset($reading['closing_manual']) ? (float) $reading['closing_manual'] : null,
                    'liters_dispensed' => $liters,
                    'revenue' => $revenue,
                    'sale_rate' => $saleRate,
                ];
            }

            $metadata['fuel_sales'] = $salesByFuel;
            $metadata['total_revenue'] = $totalRevenue;
            $metadata['total_cogs'] = $totalCogs;

            // ─────────────────────────────────────────────────────────────────
            // 2. Process Other Sales (Lubricants, etc.)
            // ─────────────────────────────────────────────────────────────────
            $otherSalesTotal = 0;
            $otherSalesDetails = [];
            if (!empty($data['other_sales'])) {
                foreach ($data['other_sales'] as $sale) {
                    $otherSalesTotal += (float) $sale['amount'];
                    $otherSalesDetails[] = [
                        'item_id' => $sale['item_id'],
                        'item_name' => $sale['item_name'],
                        'quantity' => $sale['quantity'],
                        'unit_price' => $sale['unit_price'],
                        'amount' => $sale['amount'],
                    ];
                }
            }
            $metadata['other_sales'] = $otherSalesTotal;
            $metadata['other_sales_details'] = $otherSalesDetails;
            $totalRevenue += $otherSalesTotal;

            // ─────────────────────────────────────────────────────────────────
            // 3. Process Tank Readings (calculate variance and save)
            // ─────────────────────────────────────────────────────────────────
            $tankVariances = [];
            $totalShrinkage = 0;
            $totalGain = 0;

            if (!empty($data['tank_readings'])) {
                foreach ($data['tank_readings'] as $tankData) {
                    // Get the tank to find linked item
                    $tank = \App\Modules\Inventory\Models\Warehouse::find($tankData['tank_id']);
                    $itemId = $tank?->linked_item_id;

                    if (!$itemId) {
                        continue;
                    }

                    // Calculate system expected liters:
                    // Opening (previous closing dip) + Receipts - Sales = Expected
                    $previousReading = TankReading::where('company_id', $companyId)
                        ->where('tank_id', $tankData['tank_id'])
                        ->where('reading_date', '<', $date)
                        ->orderByDesc('reading_date')
                        ->first();

                    $openingLiters = $previousReading ? (float) $previousReading->dip_measurement_liters : 0;

                    // Get today's sales for this tank's item from nozzle readings
                    $todaysSales = 0;
                    foreach ($nozzleReadingsData as $nozzleData) {
                        if ($nozzleData['item_id'] === $itemId) {
                            $todaysSales += $nozzleData['liters_dispensed'];
                        }
                    }

                    // TODO: Add fuel receipts when implemented
                    $todaysReceipts = 0;

                    $systemCalculatedLiters = round($openingLiters + $todaysReceipts - $todaysSales, 2);
                    $dipMeasurement = (float) $tankData['liters'];
                    $varianceLiters = round($dipMeasurement - $systemCalculatedLiters, 2);

                    // Determine variance type
                    $varianceType = 'none';
                    if ($varianceLiters < -0.5) {
                        $varianceType = 'loss';
                    } elseif ($varianceLiters > 0.5) {
                        $varianceType = 'gain';
                    }

                    // Get item for cost calculation
                    $item = Item::find($itemId);
                    $avgCost = (float) ($item?->avg_cost ?? 0);
                    $varianceAmount = round(abs($varianceLiters) * $avgCost, 2);

                    // Track variances for GL posting
                    if ($varianceType === 'loss' && $varianceAmount > 0) {
                        $totalShrinkage += $varianceAmount;
                        $tankVariances[] = [
                            'tank_name' => $tank->name,
                            'item_name' => $item?->name ?? 'Unknown',
                            'type' => 'loss',
                            'liters' => abs($varianceLiters),
                            'amount' => $varianceAmount,
                        ];
                    } elseif ($varianceType === 'gain' && $varianceAmount > 0) {
                        $totalGain += $varianceAmount;
                        $tankVariances[] = [
                            'tank_name' => $tank->name,
                            'item_name' => $item?->name ?? 'Unknown',
                            'type' => 'gain',
                            'liters' => $varianceLiters,
                            'amount' => $varianceAmount,
                        ];
                    }

                    // Save tank reading with calculated values
                    TankReading::updateOrCreate(
                        [
                            'company_id' => $companyId,
                            'tank_id' => $tankData['tank_id'],
                            'reading_date' => $date,
                        ],
                        [
                            'item_id' => $itemId,
                            'reading_type' => 'closing',
                            'stick_reading' => $tankData['stick_reading'] ?? null,
                            'dip_measurement_liters' => $dipMeasurement,
                            'system_calculated_liters' => $systemCalculatedLiters,
                            'variance_liters' => $varianceLiters,
                            'variance_type' => $varianceType,
                            'status' => 'posted', // Marked as posted since it's part of daily close
                            'recorded_by_user_id' => $user->id,
                        ]
                    );
                }
            }

            $metadata['tank_variances'] = $tankVariances;
            $metadata['total_shrinkage'] = $totalShrinkage;
            $metadata['total_gain'] = $totalGain;

            // ─────────────────────────────────────────────────────────────────
            // 4. Calculate Money In totals
            // ─────────────────────────────────────────────────────────────────
            $openingCash = (float) $data['opening_cash'];

            // Partner deposits (add to cash)
            $partnerDepositsTotal = 0;
            if (!empty($data['partner_deposits'])) {
                foreach ($data['partner_deposits'] as $deposit) {
                    $partnerDepositsTotal += (float) $deposit['amount'];

                    // Record partner investment
                    PartnerTransaction::create([
                        'company_id' => $companyId,
                        'partner_id' => $deposit['partner_id'],
                        'transaction_date' => $date,
                        'transaction_type' => 'investment',
                        'amount' => $deposit['amount'],
                        'description' => 'Daily deposit',
                        'payment_method' => 'cash',
                        'recorded_by_user_id' => $user->id,
                    ]);
                }
            }
            $metadata['partner_deposits'] = $partnerDepositsTotal;

            // ─────────────────────────────────────────────────────────────────
            // 4b. Process Dynamic Payment Channels (non-cash receipts)
            // ─────────────────────────────────────────────────────────────────
            $paymentReceiptsTotals = [];
            $totalNonCashReceipts = 0;
            $bankTransfersTotal = 0;
            $cardSwipesTotal = 0;
            $fuelCardsTotal = 0;

            if (!empty($data['payment_receipts'])) {
                // Get station settings to understand channel types
                $stationSettings = StationSettings::where('company_id', $companyId)->first();
                $paymentChannels = $stationSettings?->payment_channels ?? [];
                $channelTypeMap = [];
                foreach ($paymentChannels as $ch) {
                    $channelTypeMap[$ch['code']] = $ch['type'];
                }

                foreach ($data['payment_receipts'] as $channelCode => $channelData) {
                    $receiptEntries = $channelData['entries'] ?? [];
                    $channelTotal = 0;

                    foreach ($receiptEntries as $entry) {
                        $channelTotal += (float) ($entry['amount'] ?? 0);
                    }

                    $paymentReceiptsTotals[$channelCode] = $channelTotal;
                    $totalNonCashReceipts += $channelTotal;

                    // Categorize by type for GL posting
                    $channelType = $channelTypeMap[$channelCode] ?? 'bank_transfer';
                    if ($channelType === 'bank_transfer') {
                        $bankTransfersTotal += $channelTotal;
                    } elseif ($channelType === 'card_pos') {
                        $cardSwipesTotal += $channelTotal;
                    } elseif ($channelType === 'fuel_card') {
                        $fuelCardsTotal += $channelTotal;
                    } elseif ($channelType === 'mobile_wallet') {
                        // Mobile wallets typically go to bank
                        $bankTransfersTotal += $channelTotal;
                    }
                }
            }

            $metadata['payment_receipts'] = $paymentReceiptsTotals;
            $metadata['bank_transfers_received'] = $bankTransfersTotal;
            $metadata['card_swipes'] = $cardSwipesTotal;
            $metadata['fuel_cards'] = $fuelCardsTotal;

            // ─────────────────────────────────────────────────────────────────
            // 5. Calculate Money Out totals
            // ─────────────────────────────────────────────────────────────────

            // Bank deposits
            $bankDepositsTotal = 0;
            if (!empty($data['bank_deposits'])) {
                foreach ($data['bank_deposits'] as $deposit) {
                    $bankDepositsTotal += (float) $deposit['amount'];
                }
            }
            $metadata['bank_deposits'] = $bankDepositsTotal;

            // Partner withdrawals
            $partnerWithdrawalsTotal = 0;
            if (!empty($data['partner_withdrawals'])) {
                foreach ($data['partner_withdrawals'] as $withdrawal) {
                    $amount = (float) $withdrawal['amount'];
                    $partnerWithdrawalsTotal += $amount;

                    // Record partner withdrawal
                    $partner = Partner::find($withdrawal['partner_id']);
                    if ($partner) {
                        // Note: Drawing limit is informational only, not a blocker
                        // The limit is shown in UI for awareness but doesn't prevent withdrawal

                        PartnerTransaction::create([
                            'company_id' => $companyId,
                            'partner_id' => $withdrawal['partner_id'],
                            'transaction_date' => $date,
                            'transaction_type' => 'withdrawal',
                            'amount' => $amount,
                            'description' => 'Daily withdrawal',
                            'payment_method' => 'cash',
                            'recorded_by_user_id' => $user->id,
                        ]);

                        // Update current period withdrawn
                        $partner->increment('current_period_withdrawn', $amount);
                    }
                }
            }
            $metadata['partner_withdrawals'] = $partnerWithdrawalsTotal;

            // Employee advances
            $employeeAdvancesTotal = 0;
            if (!empty($data['employee_advances'])) {
                // Set RLS context for pay schema (salary_advances table has RLS enabled)
                DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
                DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);

                foreach ($data['employee_advances'] as $advance) {
                    $amount = (float) $advance['amount'];
                    $employeeAdvancesTotal += $amount;

                    // Create salary advance record
                    SalaryAdvance::create([
                        'company_id' => $companyId,
                        'employee_id' => $advance['employee_id'],
                        'advance_date' => $date,
                        'amount' => $amount,
                        'amount_outstanding' => $amount,
                        'reason' => $advance['reason'] ?? 'Daily advance',
                        'status' => 'pending',
                        'payment_method' => 'cash',
                        'recorded_by_user_id' => $user->id,
                    ]);
                }
            }
            $metadata['employee_advances'] = $employeeAdvancesTotal;

            // Amanat disbursements
            $amanatTotal = 0;
            if (!empty($data['amanat_disbursements'])) {
                foreach ($data['amanat_disbursements'] as $amanat) {
                    $amanatTotal += (float) $amanat['amount'];
                }
            }
            $metadata['amanat_disbursements'] = $amanatTotal;

            // Expenses
            $expensesTotal = 0;
            $expensesByAccount = [];
            if (!empty($data['expenses'])) {
                foreach ($data['expenses'] as $index => $expense) {
                    if (!isset($expense['account_id']) || empty($expense['account_id'])) {
                        \Log::warning("DailyClose: Skipping expense at index {$index} - missing account_id", $expense);
                        continue;
                    }
                    $amount = (float) ($expense['amount'] ?? 0);
                    if ($amount <= 0) {
                        continue;
                    }
                    $expensesTotal += $amount;

                    $accountId = $expense['account_id'];
                    if (!isset($expensesByAccount[$accountId])) {
                        $expensesByAccount[$accountId] = 0;
                    }
                    $expensesByAccount[$accountId] += $amount;
                }
            }
            $metadata['expenses'] = $expensesTotal;

            // ─────────────────────────────────────────────────────────────────
            // 6. Build Journal Entries
            // ─────────────────────────────────────────────────────────────────

            // Cash from sales (total revenue goes to cash initially)
            $cashFromSales = $totalRevenue - $cardSwipesTotal - $fuelCardsTotal - $bankTransfersTotal;
            $totalCashIn = $openingCash + $partnerDepositsTotal + $cashFromSales;
            $totalCashOut = $bankDepositsTotal + $partnerWithdrawalsTotal + $employeeAdvancesTotal + $amanatTotal + $expensesTotal;

            // Debit: Cash on Hand (opening + deposits + cash sales - withdrawals)
            $closingCash = (float) $data['closing_cash'];
            $expectedClosing = $totalCashIn - $totalCashOut;
            $variance = round($closingCash - $expectedClosing, 2);

            $metadata['expected_closing'] = $expectedClosing;
            $metadata['variance'] = $variance;

            // Store original form input for amendment pre-filling
            $metadata['form_input'] = [
                'nozzle_readings' => $data['nozzle_readings'] ?? [],
                'other_sales' => $data['other_sales'] ?? [],
                'tank_readings' => $data['tank_readings'] ?? [],
                'opening_cash' => $data['opening_cash'],
                'partner_deposits' => $data['partner_deposits'] ?? [],
                'payment_receipts' => $data['payment_receipts'] ?? [],
                'bank_deposits' => $data['bank_deposits'] ?? [],
                'partner_withdrawals' => $data['partner_withdrawals'] ?? [],
                'employee_advances' => $data['employee_advances'] ?? [],
                'amanat_disbursements' => $data['amanat_disbursements'] ?? [],
                'expenses' => $data['expenses'] ?? [],
                'closing_cash' => $data['closing_cash'],
                'notes' => $data['notes'] ?? null,
            ];

            // Revenue entry
            if ($totalRevenue > 0) {
                $entries[] = [
                    'account_id' => $accounts['fuel_sales'],
                    'type' => 'credit',
                    'amount' => round($totalRevenue, 2),
                    'description' => 'Daily fuel + oil sales',
                ];
            }

            // COGS entry
            if ($totalCogs > 0) {
                $entries[] = [
                    'account_id' => $accounts['fuel_cogs'],
                    'type' => 'debit',
                    'amount' => round($totalCogs, 2),
                    'description' => 'Cost of goods sold',
                ];
                $entries[] = [
                    'account_id' => $accounts['fuel_inventory'],
                    'type' => 'credit',
                    'amount' => round($totalCogs, 2),
                    'description' => 'Inventory reduction',
                ];
            }

            // Cash on hand (net change)
            $cashChange = $closingCash - $openingCash;
            if ($cashChange != 0) {
                $entries[] = [
                    'account_id' => $accounts['cash_on_hand'],
                    'type' => $cashChange > 0 ? 'debit' : 'credit',
                    'amount' => abs(round($cashChange, 2)),
                    'description' => 'Net cash change',
                ];
            }

            // Bank deposits (cash goes out, bank goes up)
            if ($bankDepositsTotal > 0 && $accounts['operating_bank']) {
                $entries[] = [
                    'account_id' => $accounts['operating_bank'],
                    'type' => 'debit',
                    'amount' => round($bankDepositsTotal, 2),
                    'description' => 'Cash deposited to bank',
                ];
            }

            // Card swipes clearing
            if ($cardSwipesTotal > 0 && $accounts['card_clearing']) {
                $entries[] = [
                    'account_id' => $accounts['card_clearing'],
                    'type' => 'debit',
                    'amount' => round($cardSwipesTotal, 2),
                    'description' => 'Card swipes pending settlement',
                ];
            }

            // Fuel card clearing (vendor cards)
            if ($fuelCardsTotal > 0 && $accounts['fuel_card_clearing']) {
                $entries[] = [
                    'account_id' => $accounts['fuel_card_clearing'],
                    'type' => 'debit',
                    'amount' => round($fuelCardsTotal, 2),
                    'description' => 'Fuel card sales pending settlement',
                ];
            }

            // Bank transfers received
            if ($bankTransfersTotal > 0 && $accounts['operating_bank']) {
                $entries[] = [
                    'account_id' => $accounts['operating_bank'],
                    'type' => 'debit',
                    'amount' => round($bankTransfersTotal, 2),
                    'description' => 'Customer bank transfers received',
                ];
            }

            // Partner deposits (capital contributions)
            if ($partnerDepositsTotal > 0) {
                if (!$accounts['partner_deposits']) {
                    throw new \RuntimeException('Partner deposits account missing. Set up account 2210 (Investor Deposits) or update station settings.');
                }
                $entries[] = [
                    'account_id' => $accounts['partner_deposits'],
                    'type' => 'credit',
                    'amount' => round($partnerDepositsTotal, 2),
                    'description' => 'Partner deposits',
                ];
            }

            // Partner withdrawals (drawings)
            if ($partnerWithdrawalsTotal > 0 && $accounts['partner_drawings']) {
                $entries[] = [
                    'account_id' => $accounts['partner_drawings'],
                    'type' => 'debit',
                    'amount' => round($partnerWithdrawalsTotal, 2),
                    'description' => 'Partner withdrawals',
                ];
            }

            // Employee advances
            if ($employeeAdvancesTotal > 0 && $accounts['employee_advances']) {
                $entries[] = [
                    'account_id' => $accounts['employee_advances'],
                    'type' => 'debit',
                    'amount' => round($employeeAdvancesTotal, 2),
                    'description' => 'Employee salary advances',
                ];
            }

            // Amanat disbursements (reduce liability)
            if ($amanatTotal > 0) {
                if (!$accounts['amanat_deposits']) {
                    throw new \RuntimeException('Amanat deposits account missing. Set up account 2200 (Amanat Deposits) or update station settings.');
                }
                $entries[] = [
                    'account_id' => $accounts['amanat_deposits'],
                    'type' => 'debit',
                    'amount' => round($amanatTotal, 2),
                    'description' => 'Amanat disbursements',
                ];
            }

            // Expenses by account
            foreach ($expensesByAccount as $accountId => $amount) {
                if (!$accountId) {
                    continue;
                }
                $entries[] = [
                    'account_id' => $accountId,
                    'type' => 'debit',
                    'amount' => round($amount, 2),
                    'description' => 'Daily expenses',
                ];
            }

            // Cash variance (over/short)
            if (abs($variance) > 0.01 && $accounts['cash_over_short']) {
                $entries[] = [
                    'account_id' => $accounts['cash_over_short'],
                    'type' => $variance > 0 ? 'credit' : 'debit',
                    'amount' => abs(round($variance, 2)),
                    'description' => $variance > 0 ? 'Cash over' : 'Cash short',
                ];
            }

            // Fuel shrinkage (loss): Dr Shrinkage Expense, Cr Inventory
            if ($totalShrinkage > 0 && $accounts['fuel_shrinkage']) {
                $entries[] = [
                    'account_id' => $accounts['fuel_shrinkage'],
                    'type' => 'debit',
                    'amount' => round($totalShrinkage, 2),
                    'description' => 'Fuel shrinkage loss',
                ];
                $entries[] = [
                    'account_id' => $accounts['fuel_inventory'],
                    'type' => 'credit',
                    'amount' => round($totalShrinkage, 2),
                    'description' => 'Inventory reduction (shrinkage)',
                ];
            }

            // Fuel variance gain: Dr Inventory, Cr Variance Gain
            if ($totalGain > 0 && $accounts['fuel_variance_gain']) {
                $entries[] = [
                    'account_id' => $accounts['fuel_inventory'],
                    'type' => 'debit',
                    'amount' => round($totalGain, 2),
                    'description' => 'Inventory increase (gain)',
                ];
                $entries[] = [
                    'account_id' => $accounts['fuel_variance_gain'],
                    'type' => 'credit',
                    'amount' => round($totalGain, 2),
                    'description' => 'Fuel variance gain',
                ];
            }

            // Post the transaction
            $transaction = $this->postingService->postBalancedTransaction([
                'company_id' => $companyId,
                'transaction_number' => $transactionNumber,
                'transaction_type' => 'fuel_daily_close',
                'date' => $date,
                'currency' => $currency,
                'base_currency' => $currency,
                'description' => "Daily close - {$date}",
                'reference_type' => 'fuel.daily_close',
                'metadata' => $metadata,
            ], $entries);

            // ─────────────────────────────────────────────────────────────────
            // 7. Save Nozzle Readings and update nozzle last_closing_reading
            // ─────────────────────────────────────────────────────────────────
            foreach ($nozzleReadingsData as $nozzleData) {
                NozzleReading::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'nozzle_id' => $nozzleData['nozzle_id'],
                        'reading_date' => $date,
                    ],
                    [
                        'item_id' => $nozzleData['item_id'],
                        'opening_electronic' => $nozzleData['opening_electronic'],
                        'closing_electronic' => $nozzleData['closing_electronic'],
                        'opening_manual' => $nozzleData['opening_manual'],
                        'closing_manual' => $nozzleData['closing_manual'],
                        'liters_dispensed' => $nozzleData['liters_dispensed'],
                        'recorded_by_user_id' => $user->id,
                        'daily_close_transaction_id' => $transaction->id,
                    ]
                );

                // Update nozzle's last_closing_reading for next day's opening
                $nozzleUpdate = [
                    'last_closing_reading' => $nozzleData['closing_electronic'],
                ];
                if ($nozzleData['closing_manual'] !== null) {
                    $nozzleUpdate['last_manual_reading'] = $nozzleData['closing_manual'];
                }

                Nozzle::where('id', $nozzleData['nozzle_id'])
                    ->update($nozzleUpdate);
            }

            return [
                'transaction_number' => $transactionNumber,
                'transaction_id' => $transaction->id,
                'metadata' => $metadata,
            ];
        });
    }

    /**
     * Get recent daily closes for history view.
     */
    public function getRecentCloses(string $companyId, int $days = 30): array
    {
        $closes = Transaction::where('company_id', $companyId)
            ->where('transaction_type', 'fuel_daily_close')
            ->whereNull('deleted_at')
            ->where('transaction_date', '>=', now()->subDays($days)->toDateString())
            ->orderByDesc('transaction_date')
            ->get(['id', 'transaction_number', 'transaction_date', 'metadata', 'is_locked', 'reversed_by_id', 'reversal_of_id', 'corrects_transaction_id']);

        return $closes->map(function ($t) {
            $metadata = $t->metadata ?? [];
            if (!is_array($metadata)) {
                $metadata = [];
            }

            return [
                'id' => $t->id,
                'transaction_number' => $t->transaction_number,
                'date' => $t->transaction_date->toDateString(),
                'opening_cash' => $metadata['opening_cash'] ?? 0,
                'closing_cash' => $metadata['closing_cash'] ?? 0,
                'total_revenue' => $metadata['total_revenue'] ?? 0,
                'variance' => $metadata['variance'] ?? 0,
                'status' => $t->display_status,
                'is_locked' => $t->is_locked ?? false,
                'is_amendable' => $t->isAmendable(),
                'has_amendments' => $t->reversed_by_id !== null,
            ];
        })->toArray();
    }

    private function generateTransactionNumber(string $companyId, string $date, bool $isCorrection = false): string
    {
        $base = 'FDC-' . str_replace('-', '', $date);

        // For corrections, we need to find the next available suffix
        if ($isCorrection) {
            // Find existing corrections for this date
            $existing = Transaction::where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('transaction_number', 'like', $base . '-C%')
                ->pluck('transaction_number')
                ->all();

            if (empty($existing)) {
                return $base . '-C1';
            }

            // Extract the highest correction number
            $maxNum = 0;
            foreach ($existing as $txnNum) {
                if (preg_match('/-C(\d+)$/', $txnNum, $matches)) {
                    $num = (int) $matches[1];
                    if ($num > $maxNum) {
                        $maxNum = $num;
                    }
                }
            }

            return $base . '-C' . ($maxNum + 1);
        }

        // For original entries, check if ANY entry exists for this date (including reversed)
        // This ensures users must always use the amendment flow for dates that have any history
        $exists = Transaction::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('transaction_type', 'fuel_daily_close')
            ->whereDate('transaction_date', $date)
            ->exists();

        if ($exists) {
            throw new \RuntimeException("A daily close entry already exists for {$date}. Use the amendment flow to correct it.");
        }

        return $base;
    }

    private function resolveAccounts(string $companyId): array
    {
        // First try to get accounts from station settings
        $stationSettings = StationSettings::where('company_id', $companyId)->first();

        $byCode = Account::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->get(['id', 'code', 'subtype', 'type'])
            ->keyBy('code');

        // Use settings first, then fallback to code-based lookup
        $cashOnHand = $stationSettings?->cash_account_id
            ?? $byCode->get('1050')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'cash')->orderBy('code')->value('id');

        $operatingBank = $stationSettings?->operating_bank_account_id
            ?? $byCode->get('1000')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'bank')->orderBy('code')->value('id');

        // Clearing accounts from settings
        $cardClearing = $stationSettings?->card_pos_clearing_account_id
            ?? $byCode->get('1040')?->id
            ?? $cashOnHand;

        $fuelCardClearing = $stationSettings?->fuel_card_clearing_account_id
            ?? $byCode->get('1030')?->id
            ?? $cashOnHand;

        $fuelInventory = $stationSettings?->fuel_inventory_account_id
            ?? $byCode->get('1200')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'inventory')->orderBy('code')->value('id');

        $fuelSales = $stationSettings?->fuel_sales_account_id
            ?? $byCode->get('4100')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'revenue')->orderBy('code')->value('id');

        $fuelCogs = $stationSettings?->fuel_cogs_account_id
            ?? $byCode->get('5100')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'cogs')->orderBy('code')->value('id');

        $cashOverShort = $stationSettings?->cash_over_short_account_id
            ?? $byCode->get('6180')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'expense')->orderBy('code')->value('id');

        $amanatDeposits = $byCode->get('2200')?->id;

        $partnerDeposits = $byCode->get('2210')?->id;

        // Partner drawings - from settings or fallback
        $partnerDrawings = $stationSettings?->partner_drawings_account_id
            ?? $byCode->get('3200')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('type', 'equity')->orderByDesc('code')->value('id');

        // Employee advances - from settings or fallback
        $employeeAdvances = $stationSettings?->employee_advances_account_id
            ?? $byCode->get('1150')?->id
            ?? Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('subtype', 'receivable')->orderByDesc('code')->value('id')
            ?? $cashOnHand;

        // Fuel shrinkage account - prefer 5900 (onboarding), then 6300 with Shrinkage in name
        $fuelShrinkage = $stationSettings?->fuel_shrinkage_account_id
            ?? $byCode->get('5900')?->id
            ?? Account::where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('code', '6300')
                ->where('name', 'like', '%Shrinkage%')
                ->value('id')
            ?? Account::where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('name', 'like', '%Shrinkage%')
                ->value('id');

        // Fuel variance gain account
        $fuelVarianceGain = $stationSettings?->fuel_variance_gain_account_id
            ?? $byCode->get('4900')?->id
            ?? Account::where('company_id', $companyId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->where('name', 'like', '%Variance Gain%')
                ->value('id');

        $required = [
            'cash_on_hand' => $cashOnHand,
            'operating_bank' => $operatingBank,
            'card_clearing' => $cardClearing,
            'fuel_card_clearing' => $fuelCardClearing,
            'fuel_sales' => $fuelSales,
            'fuel_cogs' => $fuelCogs,
            'fuel_inventory' => $fuelInventory,
            'cash_over_short' => $cashOverShort,
            'amanat_deposits' => $amanatDeposits,
            'partner_deposits' => $partnerDeposits,
            'partner_drawings' => $partnerDrawings,
            'employee_advances' => $employeeAdvances,
            'fuel_shrinkage' => $fuelShrinkage,
            'fuel_variance_gain' => $fuelVarianceGain,
        ];

        foreach (['cash_on_hand', 'fuel_sales', 'fuel_cogs', 'fuel_inventory'] as $key) {
            if (!$required[$key]) {
                throw new \RuntimeException("Required account missing: {$key}. Ensure fuel station COA is set up.");
            }
        }

        return $required;
    }
}
