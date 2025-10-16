<?php

namespace Modules\Accounting\Domain\Ledgers\Actions\Recurring;

use App\Models\RecurringJournalTemplate;
use App\Models\RecurringJournalTemplateLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateRecurringTemplateAction
{
    /**
     * Create a new recurring journal template.
     */
    public function execute(array $data): RecurringJournalTemplate
    {
        $validated = $this->validate($data);

        $template = RecurringJournalTemplate::create([
            'company_id' => $validated['company_id'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'frequency' => $validated['frequency'],
            'interval' => $validated['interval'] ?? 1,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'next_generation_date' => $this->calculateNextGenerationDate($validated),
            'currency' => $validated['currency'] ?? 'USD',
            'total_debit' => $this->calculateTotalDebit($validated['lines'] ?? []),
            'total_credit' => $this->calculateTotalCredit($validated['lines'] ?? []),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Create template lines
        if (! empty($validated['lines'])) {
            $this->createTemplateLines($template, $validated['lines']);
        }

        return $template->fresh(['lines']);
    }

    /**
     * Validate the template data.
     */
    protected function validate(array $data): array
    {
        $validator = Validator::make($data, [
            'company_id' => 'required|uuid|exists:companies,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'interval' => 'integer|min:1|max:999',
            'start_date' => 'required|date|before_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'currency' => 'string|max:3',
            'is_active' => 'boolean',
            'lines' => 'array|min:2',
            'lines.*.account_id' => 'required|uuid|exists:accounts,id',
            'lines.*.debit_credit' => 'required|in:debit,credit',
            'lines.*.amount' => 'required|numeric|min:0.01',
            'lines.*.description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Validate that lines are balanced
        if (! empty($validated['lines'])) {
            $totalDebit = $this->calculateTotalDebit($validated['lines']);
            $totalCredit = $this->calculateTotalCredit($validated['lines']);

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw ValidationException::withMessages([
                    'lines' => 'Template lines must be balanced (total debits must equal total credits)',
                ]);
            }
        }

        return $validated;
    }

    /**
     * Calculate the next generation date based on frequency.
     */
    protected function calculateNextGenerationDate(array $data): string
    {
        $startDate = Carbon::parse($data['start_date']);
        $frequency = $data['frequency'];
        $interval = $data['interval'] ?? 1;

        return match ($frequency) {
            'daily' => $startDate->copy()->addDays($interval)->toDateString(),
            'weekly' => $startDate->copy()->addWeeks($interval)->toDateString(),
            'monthly' => $startDate->copy()->addMonths($interval)->toDateString(),
            'quarterly' => $startDate->copy()->addQuarters($interval)->toDateString(),
            'yearly' => $startDate->copy()->addYears($interval)->toDateString(),
            default => $startDate->copy()->addMonth()->toDateString(),
        };
    }

    /**
     * Calculate total debit amount from template lines.
     */
    protected function calculateTotalDebit(array $lines): float
    {
        return collect($lines)
            ->where('debit_credit', 'debit')
            ->sum('amount');
    }

    /**
     * Calculate total credit amount from template lines.
     */
    protected function calculateTotalCredit(array $lines): float
    {
        return collect($lines)
            ->where('debit_credit', 'credit')
            ->sum('amount');
    }

    /**
     * Create template lines.
     */
    protected function createTemplateLines(RecurringJournalTemplate $template, array $lines): void
    {
        foreach ($lines as $lineData) {
            RecurringJournalTemplateLine::create([
                'template_id' => $template->id,
                'account_id' => $lineData['account_id'],
                'debit_credit' => $lineData['debit_credit'],
                'amount' => $lineData['amount'],
                'description' => $lineData['description'] ?? null,
            ]);
        }
    }
}
