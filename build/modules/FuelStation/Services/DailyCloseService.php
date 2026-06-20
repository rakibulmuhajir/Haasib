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
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\Inventory\Models\Item;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Payroll\Models\Employee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\PayrollPostingService;
use Illuminate\Support\Facades\DB;

class DailyCloseService
{
    public function __construct(
        private readonly GlPostingService $postingService,
        private readonly PayrollPostingService $payrollPostingService,
    ) {}

    private function getOpeningLitersForTank(string $companyId, string $tankId, string $itemId, string $date): float
    {
        $previousReading = TankReading::where('company_id', $companyId)
            ->where('tank_id', $tankId)
            ->where('reading_date', '<', $date)
            ->orderByDesc('reading_date')
            ->orderByDesc('created_at')
            ->first();

        if ($previousReading) {
            return (float) $previousReading->dip_measurement_liters;
        }

        return (float) StockMovement::where('company_id', $companyId)
            ->where('warehouse_id', $tankId)
            ->where('item_id', $itemId)
            ->whereDate('movement_date', '<=', $date)
            ->sum('quantity');
    }

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
            $revenuePostings = [];
            $cogsPostings = [];
            $inventoryPostings = [];
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
                    $fuelLabel = $item?->name ?? str_replace('_', ' ', ucfirst($fuelCategory));
                    if (!isset($salesByFuel[$fuelCategory])) {
                        $salesByFuel[$fuelCategory] = ['liters' => 0, 'revenue' => 0, 'cogs' => 0];
                    }
                    $salesByFuel[$fuelCategory]['liters'] += $liters;
                    $salesByFuel[$fuelCategory]['revenue'] += $revenue;
                    $salesByFuel[$fuelCategory]['cogs'] += $cogs;

                    $this->addGroupedPosting(
                        $revenuePostings,
                        $item?->income_account_id ?: $accounts['fuel_sales'],
                        $revenue,
                        $fuelLabel
                    );

                    if ($cogs > 0) {
                        $this->addGroupedPosting(
                            $cogsPostings,
                            $item?->expense_account_id ?: $accounts['fuel_cogs'],
                            $cogs,
                            $fuelLabel
                        );
                        $this->addGroupedPosting(
                            $inventoryPostings,
                            $item?->asset_account_id ?: $accounts['fuel_inventory'],
                            $cogs,
                            $fuelLabel
                        );
                    }
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
                    $amount = (float) $sale['amount'];
                    $otherSalesTotal += $amount;
                    $item = Item::where('id', $sale['item_id'])
                        ->where('company_id', $companyId)
                        ->first();
                    $label = $item?->name ?? ($sale['item_name'] ?? 'Other sales');

                    if ($amount > 0) {
                        $this->addGroupedPosting(
                            $revenuePostings,
                            $item?->income_account_id ?: $accounts['fuel_sales'],
                            $amount,
                            $label
                        );
                    }

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
                    $tank = \App\Modules\Inventory\Models\Warehouse::where('company_id', $companyId)
                        ->find($tankData['tank_id']);
                    $itemId = $tank?->linked_item_id;

                    if (!$itemId) {
                        continue;
                    }

                    // Calculate system expected liters:
                    // Opening (previous closing dip, or stock baseline for first close) + Receipts - Sales = Expected
                    $openingLiters = $this->getOpeningLitersForTank($companyId, $tankData['tank_id'], $itemId, $date);

                    // Get today's sales for this tank's item from nozzle readings and open/bulk product sales.
                    $todaysSales = 0;
                    foreach ($nozzleReadingsData as $nozzleData) {
                        if ($nozzleData['item_id'] === $itemId) {
                            $todaysSales += $nozzleData['liters_dispensed'];
                        }
                    }
                    foreach ($otherSalesDetails as $sale) {
                        if (($sale['item_id'] ?? null) === $itemId) {
                            $todaysSales += (float) ($sale['quantity'] ?? 0);
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
            $paymentReceiptPostings = [];
            $totalNonCashReceipts = 0;
            $bankTransfersTotal = 0;
            $cardSwipesTotal = 0;
            $fuelCardsTotal = 0;

            if (!empty($data['payment_receipts'])) {
                // Get station settings to understand channel types
                $stationSettings = StationSettings::where('company_id', $companyId)->first();
                $paymentChannels = $stationSettings?->payment_channels ?? [];
                $channelMap = [];
                foreach ($paymentChannels as $ch) {
                    $channelMap[$ch['code']] = $ch;
                }

                foreach ($data['payment_receipts'] as $channelCode => $channelData) {
                    $receiptEntries = $channelData['entries'] ?? [];
                    $channelTotal = 0;

                    foreach ($receiptEntries as $entry) {
                        $channelTotal += (float) ($entry['amount'] ?? 0);
                    }

                    $paymentReceiptsTotals[$channelCode] = $channelTotal;
                    if ($channelTotal <= 0) {
                        continue;
                    }

                    // Categorize by type for GL posting
                    $channel = $channelMap[$channelCode] ?? [
                        'code' => $channelCode,
                        'label' => $channelCode,
                        'type' => 'bank_transfer',
                    ];
                    $channelType = $channel['type'] ?? 'bank_transfer';
                    $destinationAccountId = $this->resolvePaymentChannelAccount($channel, $accounts);

                    if ($channelType !== 'cash') {
                        $totalNonCashReceipts += $channelTotal;
                        $paymentReceiptPostings[] = [
                            'channel_code' => $channelCode,
                            'channel_label' => $channel['label'] ?? $channelCode,
                            'channel_type' => $channelType,
                            'account_id' => $destinationAccountId,
                            'amount' => $channelTotal,
                        ];
                    }

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
            $metadata['payment_receipt_postings'] = $paymentReceiptPostings;
            $metadata['bank_transfers_received'] = $bankTransfersTotal;
            $metadata['card_swipes'] = $cardSwipesTotal;
            $metadata['fuel_cards'] = $fuelCardsTotal;

            // ─────────────────────────────────────────────────────────────────
            // 5. Calculate Money Out totals
            // ─────────────────────────────────────────────────────────────────

            // Bank deposits
            $bankDepositsTotal = 0;
            $bankDepositsByAccount = [];
            if (!empty($data['bank_deposits'])) {
                foreach ($data['bank_deposits'] as $deposit) {
                    $amount = (float) $deposit['amount'];
                    if ($amount <= 0) {
                        continue;
                    }

                    $bankDepositsTotal += $amount;
                    $accountId = $deposit['bank_account_id'] ?? $accounts['operating_bank'];
                    if (!isset($bankDepositsByAccount[$accountId])) {
                        $bankDepositsByAccount[$accountId] = 0;
                    }
                    $bankDepositsByAccount[$accountId] += $amount;
                }
            }
            $metadata['bank_deposits'] = $bankDepositsTotal;
            $metadata['bank_deposits_by_account'] = $bankDepositsByAccount;

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

            // Approved salary payouts paid from station cash.
            $payrollPayoutsTotal = 0;
            $payrollPayoutDetails = [];
            $payrollPayoutIds = [];
            if (!empty($data['payroll_payouts'])) {
                DB::select("SELECT set_config('app.current_company_id', ?, false)", [$companyId]);
                DB::select("SELECT set_config('app.current_user_id', ?, false)", [$user->id]);

                foreach ($data['payroll_payouts'] as $payout) {
                    $amount = round((float) ($payout['amount'] ?? 0), 2);
                    if ($amount <= 0) {
                        continue;
                    }

                    $payslip = Payslip::where('company_id', $companyId)
                        ->where('status', 'approved')
                        ->whereNull('payment_gl_transaction_id')
                        ->whereDate('approved_at', $date)
                        ->with('employee:id,first_name,last_name,employee_number')
                        ->lockForUpdate()
                        ->find($payout['payslip_id'] ?? null);

                    if (!$payslip) {
                        throw new \RuntimeException('One approved salary payout is no longer available for this daily close.');
                    }

                    $netPay = round((float) $payslip->net_pay, 2);
                    if (abs($amount - $netPay) > 0.01) {
                        throw new \RuntimeException("Salary payout for {$payslip->payslip_number} must equal remaining net salary.");
                    }

                    $payrollPayoutsTotal = round($payrollPayoutsTotal + $netPay, 2);
                    $payrollPayoutIds[] = $payslip->id;
                    $payrollPayoutDetails[] = [
                        'payslip_id' => $payslip->id,
                        'payslip_number' => $payslip->payslip_number,
                        'employee_id' => $payslip->employee_id,
                        'employee_name' => trim(($payslip->employee?->first_name ?? '') . ' ' . ($payslip->employee?->last_name ?? '')) ?: 'Employee',
                        'employee_number' => $payslip->employee?->employee_number,
                        'amount' => $netPay,
                    ];
                }
            }
            $metadata['payroll_payouts'] = $payrollPayoutsTotal;
            $metadata['payroll_payout_details'] = $payrollPayoutDetails;

            // Amanat disbursements
            $amanatTotal = 0;
            $amanatDetails = [];
            if (!empty($data['amanat_disbursements'])) {
                foreach ($data['amanat_disbursements'] as $amanat) {
                    $amount = (float) $amanat['amount'];
                    if ($amount <= 0) {
                        continue;
                    }

                    $customerId = $amanat['customer_id'] ?? null;
                    if (!$customerId) {
                        throw new \RuntimeException('Select an Amanat depositor before posting a disbursement.');
                    }

                    $profile = CustomerProfile::where('company_id', $companyId)
                        ->where('customer_id', $customerId)
                        ->where('is_amanat_holder', true)
                        ->with('customer:id,name')
                        ->lockForUpdate()
                        ->first();

                    if (!$profile) {
                        throw new \RuntimeException('Selected Amanat depositor was not found.');
                    }

                    if ($amount > (float) $profile->amanat_balance) {
                        $name = $profile->customer?->name ?? 'Selected Amanat depositor';
                        throw new \RuntimeException("{$name} has only {$profile->amanat_balance} available in Amanat.");
                    }

                    AmanatTransaction::create([
                        'company_id' => $companyId,
                        'customer_id' => $customerId,
                        'transaction_type' => AmanatTransaction::TYPE_WITHDRAWAL,
                        'amount' => $amount,
                        'reference' => 'Daily close ' . $date,
                        'notes' => $amanat['notes'] ?? 'Daily close cash disbursement',
                        'recorded_by_user_id' => $user->id,
                    ]);

                    $profile->adjustAmanatBalance(-$amount);

                    $amanatTotal += $amount;
                    $amanatDetails[] = [
                        'customer_id' => $customerId,
                        'customer_name' => $profile->customer?->name ?? ($amanat['customer_name'] ?? null),
                        'amount' => $amount,
                    ];
                }
            }
            $metadata['amanat_disbursements'] = $amanatTotal;
            $metadata['amanat_disbursement_details'] = $amanatDetails;

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
            $cashFromSales = $totalRevenue - $totalNonCashReceipts;
            $totalCashIn = $openingCash + $partnerDepositsTotal + $cashFromSales;
            $totalCashOut = $bankDepositsTotal + $partnerWithdrawalsTotal + $employeeAdvancesTotal + $payrollPayoutsTotal + $amanatTotal + $expensesTotal;

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
                'payroll_payouts' => $payrollPayoutDetails,
                'amanat_disbursements' => $data['amanat_disbursements'] ?? [],
                'expenses' => $data['expenses'] ?? [],
                'closing_cash' => $data['closing_cash'],
                'notes' => $data['notes'] ?? null,
            ];

            // Revenue entries. Prefer product-level mappings; station settings are fallback defaults.
            foreach ($revenuePostings as $posting) {
                $entries[] = [
                    'account_id' => $posting['account_id'],
                    'type' => 'credit',
                    'amount' => round($posting['amount'], 2),
                    'description' => 'Daily sales - ' . implode(', ', $posting['labels']),
                ];
            }

            // COGS entries. Prefer product-level mappings; station settings are fallback defaults.
            foreach ($cogsPostings as $posting) {
                $entries[] = [
                    'account_id' => $posting['account_id'],
                    'type' => 'debit',
                    'amount' => round($posting['amount'], 2),
                    'description' => 'Cost of goods sold - ' . implode(', ', $posting['labels']),
                ];
            }

            foreach ($inventoryPostings as $posting) {
                $entries[] = [
                    'account_id' => $posting['account_id'],
                    'type' => 'credit',
                    'amount' => round($posting['amount'], 2),
                    'description' => 'Inventory reduction - ' . implode(', ', $posting['labels']),
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

            // Bank deposits (cash goes out, selected bank goes up)
            foreach ($bankDepositsByAccount as $bankAccountId => $amount) {
                $entries[] = [
                    'account_id' => $bankAccountId,
                    'type' => 'debit',
                    'amount' => round($amount, 2),
                    'description' => 'Cash deposited to bank',
                ];
            }

            // Non-cash payment channels land in their configured clearing/bank accounts.
            foreach ($paymentReceiptPostings as $posting) {
                $entries[] = [
                    'account_id' => $posting['account_id'],
                    'type' => 'debit',
                    'amount' => round($posting['amount'], 2),
                    'description' => ($posting['channel_label'] ?? 'Payment channel') . ' receipts',
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

            if ($payrollPayoutsTotal > 0) {
                $payrollAccounts = $this->payrollPostingService->ensureDefaultPayrollAccounts($companyId);
                $entries[] = [
                    'account_id' => $payrollAccounts['payroll_payable']['id'],
                    'type' => 'debit',
                    'amount' => round($payrollPayoutsTotal, 2),
                    'description' => 'Approved salary payouts',
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

            if (!empty($payrollPayoutIds)) {
                Payslip::where('company_id', $companyId)
                    ->whereIn('id', $payrollPayoutIds)
                    ->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'payment_method' => 'cash',
                        'payment_reference' => $transactionNumber,
                        'payment_gl_transaction_id' => $transaction->id,
                    ]);
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

    private function resolvePaymentChannelAccount(array $channel, array $accounts): string
    {
        $type = $channel['type'] ?? 'bank_transfer';
        $label = $channel['label'] ?? $channel['code'] ?? 'payment channel';

        $accountId = match ($type) {
            'cash' => $accounts['cash_on_hand'] ?? null,
            'bank_transfer' => $channel['bank_account_id'] ?? $accounts['operating_bank'] ?? null,
            'card_pos' => $channel['clearing_account_id'] ?? $accounts['card_clearing'] ?? null,
            'fuel_card' => $channel['clearing_account_id'] ?? $accounts['fuel_card_clearing'] ?? null,
            'mobile_wallet' => $channel['clearing_account_id'] ?? $channel['bank_account_id'] ?? $accounts['operating_bank'] ?? null,
            default => $channel['bank_account_id'] ?? $channel['clearing_account_id'] ?? $accounts['operating_bank'] ?? null,
        };

        if (!$accountId) {
            throw new \RuntimeException("No GL account configured for payment channel: {$label}.");
        }

        return $accountId;
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

    private function addGroupedPosting(array &$postings, string $accountId, float $amount, string $label): void
    {
        if ($amount <= 0) {
            return;
        }

        $label = trim($label) !== '' ? trim($label) : 'Fuel';

        if (!isset($postings[$accountId])) {
            $postings[$accountId] = [
                'account_id' => $accountId,
                'amount' => 0,
                'labels' => [],
            ];
        }

        $postings[$accountId]['amount'] += $amount;
        if (!in_array($label, $postings[$accountId]['labels'], true)) {
            $postings[$accountId]['labels'][] = $label;
        }
    }
}
