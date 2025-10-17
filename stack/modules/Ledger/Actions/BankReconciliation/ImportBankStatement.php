<?php

namespace Modules\Ledger\Actions\BankReconciliation;

use App\Models\BankStatement;
use App\Models\ChartOfAccount;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Modules\Ledger\Jobs\NormalizeBankStatement;

class ImportBankStatement
{
    public function __construct(
        private readonly User $user,
        private readonly ChartOfAccount $bankAccount,
        private readonly UploadedFile $file,
        private readonly string $periodStart,
        private readonly string $periodEnd,
        private readonly float $openingBalance,
        private readonly float $closingBalance,
        private readonly string $currency
    ) {}

    public function execute(): BankStatement
    {
        $this->validateAccountOwnership();
        $this->validateFileFormat();
        $this->validateDateRange();
        $this->checkForDuplicates();

        $statement = $this->createBankStatement();
        $this->storeFile($statement);
        $this->dispatchNormalizationJob($statement);

        return $statement;
    }

    private function validateAccountOwnership(): void
    {
        if ($this->bankAccount->company_id !== $this->user->current_company_id) {
            throw new \InvalidArgumentException('Bank account does not belong to the current company');
        }
    }

    private function validateFileFormat(): void
    {
        $allowedFormats = ['csv', 'ofx', 'qfx'];
        $extension = strtolower($this->file->getClientOriginalExtension());

        if (! in_array($extension, $allowedFormats)) {
            throw new \InvalidArgumentException("File format {$extension} is not supported. Allowed formats: ".implode(', ', $allowedFormats));
        }

        $maxSize = config('filesystems.max_file_size', 10240); // 10MB default
        if ($this->file->getSize() > $maxSize * 1024) {
            throw new \InvalidArgumentException('File size exceeds maximum allowed limit');
        }
    }

    private function validateDateRange(): void
    {
        $startDate = \Carbon\Carbon::parse($this->periodStart);
        $endDate = \Carbon\Carbon::parse($this->periodEnd);

        if ($startDate->greaterThan($endDate)) {
            throw new \InvalidArgumentException('Period start date must be before end date');
        }

        if ($startDate->greaterThan(now()) || $endDate->greaterThan(now())) {
            throw new \InvalidArgumentException('Statement dates cannot be in the future');
        }
    }

    private function checkForDuplicates(): void
    {
        $statementUid = $this->generateStatementUid();

        $existingStatement = BankStatement::where('company_id', $this->user->current_company_id)
            ->where('ledger_account_id', $this->bankAccount->id)
            ->where('statement_uid', $statementUid)
            ->first();

        if ($existingStatement) {
            throw new \Exception('A statement for this period and account already exists', 409);
        }
    }

    private function createBankStatement(): BankStatement
    {
        $statementUid = $this->generateStatementUid();
        $originalName = $this->file->getClientOriginalName();
        $format = strtolower($this->file->getClientOriginalExtension());

        return BankStatement::create([
            'company_id' => $this->user->current_company_id,
            'ledger_account_id' => $this->bankAccount->id,
            'statement_uid' => $statementUid,
            'statement_name' => $originalName,
            'opening_balance' => $this->openingBalance,
            'closing_balance' => $this->closingBalance,
            'currency' => $this->currency,
            'statement_start_date' => $this->periodStart,
            'statement_end_date' => $this->periodEnd,
            'format' => $format,
            'imported_by' => $this->user->id,
            'imported_at' => now(),
            'status' => 'pending',
        ]);
    }

    private function storeFile(BankStatement $statement): void
    {
        $disk = config('bank-statements.disk', 'bank-statements');
        $fileName = $this->generateFileName($statement);

        $path = $this->file->storeAs(
            $this->getStoragePath($statement),
            $fileName,
            $disk
        );

        $statement->update(['file_path' => $path]);
    }

    private function dispatchNormalizationJob(BankStatement $statement): void
    {
        NormalizeBankStatement::dispatch($statement);
    }

    private function generateStatementUid(): string
    {
        $data = [
            'account_id' => $this->bankAccount->id,
            'period_start' => $this->periodStart,
            'period_end' => $this->periodEnd,
            'file_hash' => hash('sha256', $this->file->get()),
        ];

        return hash('sha256', serialize($data));
    }

    private function generateFileName(BankStatement $statement): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $extension = $this->file->getClientOriginalExtension();

        return "{$statement->statement_uid}_{$timestamp}.{$extension}";
    }

    private function getStoragePath(BankStatement $statement): string
    {
        $year = $statement->statement_start_date->format('Y');
        $month = $statement->statement_start_date->format('m');

        return "bank-statements/{$statement->company_id}/{$statement->ledger_account_id}/{$year}/{$month}";
    }

    public static function fromRequest(User $user, array $data): self
    {
        return new self(
            user: $user,
            bankAccount: ChartOfAccount::findOrFail($data['bank_account_id']),
            file: $data['statement_file'],
            periodStart: $data['statement_period_start'],
            periodEnd: $data['statement_period_end'],
            openingBalance: (float) $data['opening_balance'],
            closingBalance: (float) $data['closing_balance'],
            currency: $data['currency'],
        );
    }
}
