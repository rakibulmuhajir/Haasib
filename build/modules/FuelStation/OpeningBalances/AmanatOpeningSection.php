<?php

namespace App\Modules\FuelStation\OpeningBalances;

use App\Models\Company;
use App\Modules\Accounting\Actions\OpeningBalance\SaveAction;
use App\Modules\Accounting\Extensions\OpeningBalanceSection;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Money customers have left with the station (amanat), as an opening-balance section.
 * Registered with the core's OpeningBalanceSections by the FuelStation provider.
 */
class AmanatOpeningSection implements OpeningBalanceSection
{
    public function key(): string
    {
        return 'amanat';
    }

    public function label(): string
    {
        return 'Amanat depositors';
    }

    public function description(): string
    {
        return 'Money customers have left with the station';
    }

    public function partyLabel(): string
    {
        return 'Depositor';
    }

    public function amountLabel(): string
    {
        return 'Held';
    }

    public function hint(): string
    {
        return 'Held as amanat, used up against fuel.';
    }

    public function party(): string
    {
        return 'customer';
    }

    public function side(): string
    {
        return 'liability';
    }

    public function available(Company $company): bool
    {
        return $company->hasModule('fuel_station');
    }

    public function ready(Company $company): bool
    {
        return $this->available($company) && $this->accountId($company->id) !== null;
    }

    public function rules(): array
    {
        return [
            'amanat' => 'nullable|array',
            'amanat.*.customer_id' => ['required', 'uuid', Rule::exists(Customer::class, 'id')],
            'amanat.*.amount' => 'required|numeric|gt:0',
        ];
    }

    private function accountId(string $companyId): ?string
    {
        return Account::where('company_id', $companyId)->whereNull('deleted_at')->where('is_active', true)->where('code', '2200')->value('id');
    }

    public function addLines(string $companyId, array $params, array &$lines, array &$pending): void
    {
        $rows = array_filter($params['amanat'] ?? [], fn ($r) => (float) $r['amount'] > 0);
        if (empty($rows)) {
            return;
        }
        $accountId = $this->accountId($companyId);
        if (! $accountId) {
            throw ValidationException::withMessages(['amanat' => 'Set up account 2200 (Customer Amanat Deposits) first.']);
        }
        foreach ($rows as $row) {
            $customer = Customer::where('company_id', $companyId)->findOrFail($row['customer_id']);
            $amount = round((float) $row['amount'], 2);
            $lineIndex = count($lines);
            $lines[] = ['account_id' => $accountId, 'type' => 'credit', 'amount' => $amount, 'description' => "Opening amanat — {$customer->name}"];
            $pending[] = function (array $entryIdsByLine) use ($companyId, $customer, $amount, $lineIndex) {
                $profile = CustomerProfile::getOrCreateForCustomer($companyId, $customer->id);
                if (! $profile->is_amanat_holder) {
                    $profile->update(['is_amanat_holder' => true]);
                }
                AmanatTransaction::create([
                    'company_id' => $companyId,
                    'customer_id' => $customer->id,
                    'transaction_type' => AmanatTransaction::TYPE_DEPOSIT,
                    'amount' => $amount,
                    'reference' => SaveAction::MARK,
                    'notes' => 'Opening balance',
                    'recorded_by_user_id' => Auth::id(),
                    'journal_entry_id' => $entryIdsByLine[$lineIndex],
                ]);
                $profile->adjustAmanatBalance($amount);
            };
        }
    }

    public function prepareReentry(string $companyId, array $entryIds): mixed
    {
        $amanats = AmanatTransaction::whereIn('journal_entry_id', $entryIds)->get();
        foreach ($amanats as $amanat) {
            $profile = CustomerProfile::where('company_id', $companyId)->where('customer_id', $amanat->customer_id)->first();
            $balance = $profile ? (float) $profile->amanat_balance : 0.0;
            if ($balance < (float) $amanat->amount) {
                throw ValidationException::withMessages(['amanat' => 'An opening amanat balance has already been drawn down; cannot re-enter opening balances.']);
            }
        }

        return $amanats;
    }

    public function undo(string $companyId, mixed $state): void
    {
        foreach ($state as $amanat) {
            $profile = CustomerProfile::getOrCreateForCustomer($companyId, $amanat->customer_id);
            $profile->adjustAmanatBalance(-(float) $amanat->amount);
            $amanat->delete();
        }
    }

    public function rows(string $companyId, array $entryIds): array
    {
        return AmanatTransaction::whereIn('journal_entry_id', $entryIds)->with('customer:id,name')->get()
            ->map(fn ($t) => ['customer_id' => $t->customer_id, 'customer_name' => $t->customer?->name, 'amount' => (float) $t->amount])->values()->all();
    }
}
