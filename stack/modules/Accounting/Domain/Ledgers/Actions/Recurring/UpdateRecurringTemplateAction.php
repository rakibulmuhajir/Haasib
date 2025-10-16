<?php

namespace Modules\Accounting\Domain\Ledgers\Actions\Recurring;

use App\Models\RecurringJournalTemplate;
use App\Models\RecurringJournalTemplateLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateRecurringTemplateAction
{
    /**
     * Update an existing recurring journal template.
     */
    public function execute(RecurringJournalTemplate $template, array $data): RecurringJournalTemplate
    {
        $validated = $this->validate($data, $template);

        $updateData = [
            'name' => $validated['name'] ?? $template->name,
            'description' => $validated['description'] ?? $template->description,
            'frequency' => $validated['frequency'] ?? $template->frequency,
            'interval' => $validated['interval'] ?? $template->interval,
            'start_date' => $validated['start_date'] ?? $template->start_date,
            'end_date' => $validated['end_date'] ?? $template->end_date,
            'currency' => $validated['currency'] ?? $template->currency,
            'is_active' => $validated['is_active'] ?? $template->is_active,
        ];

        // Recalculate next generation date if frequency or dates changed
        if (isset($validated['frequency']) || isset($validated['start_date']) || isset($validated['interval'])) {
            $updateData['next_generation_date'] = $this->calculateNextGenerationDate(
                array_merge($template->toArray(), $validated)
            );
        }

        // Update totals if lines provided
        if (isset($validated['lines'])) {
            $updateData['total_debit'] = $this->calculateTotalDebit($validated['lines']);
            $updateData['total_credit'] = $this->calculateTotalCredit($validated['lines']);
        }

        $template->update($updateData);

        // Update template lines if provided
        if (isset($validated['lines'])) {
            $this->updateTemplateLines($template, $validated['lines']);
        }

        return $template->fresh(['lines']);
    }

    /**
     * Validate the template data for updates.
     */
    protected function validate(array $data, RecurringJournalTemplate $template): array
    {
        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'frequency' => 'sometimes|required|in:daily,weekly,monthly,quarterly,yearly',
            'interval' => 'sometimes|integer|min:1|max:999',
            'start_date' => 'sometimes|required|date|before_or_equal:today',
            'end_date' => 'nullable|date|after:start_date',
            'currency' => 'sometimes|string|max:3',
            'is_active' => 'sometimes|boolean',
            'lines' => 'sometimes|array|min:2',
            'lines.*.account_id' => 'required|uuid|exists:accounts,id',
            'lines.*.debit_credit' => 'required|in:debit,credit',
            'lines.*.amount' => 'required|numeric|min:0.01',
            'lines.*.description' => 'nullable|string|max:255',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        // Validate that lines are balanced if provided
        if (isset($validated['lines'])) {
            $totalDebit = $this->calculateTotalDebit($validated['lines']);
            $totalCredit = $this->calculateTotalCredit($validated['lines']);

            if (abs($totalDebit - $totalCredit) > 0.01) {
                throw ValidationException::withMessages([
                    'lines' => 'Template lines must be balanced (total debits must equal total credits)',
                ]);
            }
        }

        // Validate date logic
        $startDate = Carbon::parse($validated['start_date'] ?? $template->start_date);
        $endDate = $validated['end_date'] ?? $template->end_date;

        if ($endDate && Carbon::parse($endDate)->lt($startDate)) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be after start date',
            ]);
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
     * Update template lines - remove existing and create new ones.
     */
    protected function updateTemplateLines(RecurringJournalTemplate $template, array $lines): void
    {
        // Remove existing lines
        $template->lines()->delete();

        // Create new lines
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
