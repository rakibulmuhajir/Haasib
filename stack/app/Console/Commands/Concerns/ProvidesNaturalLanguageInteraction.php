<?php

namespace App\Console\Commands\Concerns;

trait ProvidesNaturalLanguageInteraction
{
    /**
     * Parse natural language input into structured command arguments.
     */
    protected function parseNaturalLanguageInput(string $input): array
    {
        $parsed = [
            'action' => null,
            'company' => null,
            'customer' => null,
            'amount' => null,
            'due_date' => null,
            'items' => [],
            'terms' => [],
            'flags' => [],
        ];

        // Normalize input
        $input = strtolower(trim($input));

        // Extract action
        $parsed['action'] = $this->extractAction($input);

        // Extract company reference
        $parsed['company'] = $this->extractCompany($input);

        // Extract customer information
        $parsed['customer'] = $this->extractCustomer($input);

        // Extract monetary amounts
        $parsed['amount'] = $this->extractAmount($input);

        // Extract dates
        $parsed['due_date'] = $this->extractDate($input);

        // Extract line items
        $parsed['items'] = $this->extractItems($input);

        // Extract terms and conditions
        $parsed['terms'] = $this->extractTerms($input);

        // Extract flags (send, post, etc.)
        $parsed['flags'] = $this->extractFlags($input);

        return $parsed;
    }

    /**
     * Extract the primary action from natural language.
     */
    protected function extractAction(string $input): ?string
    {
        $actions = [
            'create' => ['create', 'new', 'add', 'make', 'generate'],
            'update' => ['update', 'modify', 'change', 'edit'],
            'send' => ['send', 'email', 'dispatch'],
            'post' => ['post', 'record', 'register'],
            'cancel' => ['cancel', 'void', 'delete'],
            'list' => ['list', 'show', 'display', 'view'],
            'duplicate' => ['duplicate', 'copy', 'clone'],
            'pdf' => ['pdf', 'export', 'download'],
        ];

        foreach ($actions as $action => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($input, $keyword)) {
                    return $action;
                }
            }
        }

        return null;
    }

    /**
     * Extract company reference from input.
     */
    protected function extractCompany(string $input): ?string
    {
        $patterns = [
            '/(?:for|in|at|company)\s+([a-z0-9\s\-]+)/i',
            '/@([a-z0-9\s\-]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Extract customer information from input.
     */
    protected function extractCustomer(string $input): ?string
    {
        $patterns = [
            '/(?:customer|client|for)\s+([a-z0-9\s\-@\.]+)/i',
            '/(?:invoice|bill)\s+(?:to|for)\s+([a-z0-9\s\-@\.]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches)) {
                return trim($matches[1]);
            }
        }

        return null;
    }

    /**
     * Extract monetary amounts from input.
     */
    protected function extractAmount(string $input): ?float
    {
        $patterns = [
            '/(?:amount|total|for)\s+\$?(\d+(?:\.\d{2})?)/i',
            '/\$(\d+(?:\.\d{2})?)/',
            '/(\d+(?:\.\d{2})?)\s*(?:dollars?|usd)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches)) {
                return (float) $matches[1];
            }
        }

        return null;
    }

    /**
     * Extract dates from input.
     */
    protected function extractDate(string $input): ?string
    {
        $patterns = [
            '/(?:due|pay|expire)s?\s+(?:on|by)?\s?(\d{4}-\d{2}-\d{2})/',
            '/(?:due|pay|expire)s?\s+(?:on|by)?\s?(\d{1,2}\/\d{1,2}\/\d{4})/',
            '/(?:due|pay|expire)s?\s+(?:on|by)?\s?(\w+\s+\d{1,2},?\s+\d{4})/',
            '/(\d{1,2})\s+days?\s+(?:from|after)\s+now/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $input, $matches)) {
                $dateStr = trim($matches[1]);

                try {
                    // Try to parse the date
                    if (preg_match('/(\d{1,2})\s+days?\s+(?:from|after)\s+now/i', $dateStr)) {
                        return now()->addDays((int) $dateStr)->toDateString();
                    }

                    return \Carbon\Carbon::parse($dateStr)->toDateString();
                } catch (\Exception $e) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Extract line items from input.
     */
    protected function extractItems(string $input): array
    {
        $items = [];

        // Pattern for "item for $amount" or "service at $price"
        $patterns = [
            '/([a-z0-9\s\-]+)\s+(?:for|at)\s+\$?(\d+(?:\.\d{2})?)/i',
            '/(\d+)\s+(?:x|of)?\s*([a-z0-9\s\-]+)\s+(?:for|at)?\s*\$?(\d+(?:\.\d{2})?)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $input, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    if (count($match) === 3) {
                        // Simple description and price
                        $items[] = [
                            'description' => trim($match[1]),
                            'quantity' => 1,
                            'unit_price' => (float) $match[2],
                            'total' => (float) $match[2],
                        ];
                    } elseif (count($match) === 4) {
                        // Quantity, description, and price
                        $quantity = (int) $match[1];
                        $description = trim($match[2]);
                        $unitPrice = (float) $match[3];

                        $items[] = [
                            'description' => $description,
                            'quantity' => $quantity,
                            'unit_price' => $unitPrice,
                            'total' => $quantity * $unitPrice,
                        ];
                    }
                }
            }
        }

        return $items;
    }

    /**
     * Extract terms and conditions from input.
     */
    protected function extractTerms(string $input): array
    {
        $terms = [];

        $patterns = [
            '/(?:terms?|payment)\s+(?:due|in)\s+(\d+)\s+days?/i' => 'payment_terms',
            '/(?:discount|late\s+fee)\s+(?:of\s+)?(\d+(?:\.\d{2})?)%/i' => 'late_fee_percent',
            '/(?:interest|penalty)\s+(?:rate\s+)?(\d+(?:\.\d{2})?)%/i' => 'interest_rate',
        ];

        foreach ($patterns as $pattern => $termType) {
            if (preg_match($pattern, $input, $matches)) {
                $terms[$termType] = $matches[1];
            }
        }

        return $terms;
    }

    /**
     * Extract command flags from input.
     */
    protected function extractFlags(string $input): array
    {
        $flags = [];

        $flagPatterns = [
            'send' => ['send', 'email', 'dispatch'],
            'post' => ['post', 'record', 'register'],
            'draft' => ['draft', 'save', 'temporary'],
            'urgent' => ['urgent', 'asap', 'immediate'],
            'reminder' => ['reminder', 'follow-up', 'chase'],
        ];

        foreach ($flagPatterns as $flag => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($input, $keyword)) {
                    $flags[] = $flag;
                    break;
                }
            }
        }

        return array_unique($flags);
    }

    /**
     * Suggest command completion based on partial input.
     */
    protected function suggestCompletion(string $partialInput): array
    {
        $suggestions = [];

        $commonPatterns = [
            'create invoice for {customer} for {amount}' => 'create:invoice',
            'send invoice #{id} to {customer}' => 'send:invoice',
            'list invoices for {company}' => 'list:invoices',
            'show invoice #{id}' => 'show:invoice',
            'mark invoice #{id} as paid' => 'pay:invoice',
            'cancel invoice #{id}' => 'cancel:invoice',
            'duplicate invoice #{id}' => 'duplicate:invoice',
            'generate pdf for invoice #{id}' => 'pdf:invoice',
        ];

        foreach ($commonPatterns as $pattern => $command) {
            $similarity = similar_text(strtolower($partialInput), strtolower($pattern), $percent);

            if ($percent > 60) {
                $suggestions[] = [
                    'command' => $command,
                    'pattern' => $pattern,
                    'confidence' => $percent,
                ];
            }
        }

        // Sort by confidence
        usort($suggestions, fn ($a, $b) => $b['confidence'] <=> $a['confidence']);

        return array_slice($suggestions, 0, 5);
    }

    /**
     * Confirm natural language interpretation with user.
     */
    protected function confirmInterpretation(array $parsed): bool
    {
        $this->info('I interpreted your request as:');
        $this->line('');

        if ($parsed['action']) {
            $this->line("Action: {$parsed['action']}");
        }

        if ($parsed['customer']) {
            $this->line("Customer: {$parsed['customer']}");
        }

        if ($parsed['amount']) {
            $this->line("Amount: \${$parsed['amount']}");
        }

        if ($parsed['due_date']) {
            $this->line("Due date: {$parsed['due_date']}");
        }

        if (! empty($parsed['items'])) {
            $this->line('Items:');
            foreach ($parsed['items'] as $item) {
                $this->line("  - {$item['description']} x{$item['quantity']} @ \${$item['unit_price']}");
            }
        }

        if (! empty($parsed['flags'])) {
            $this->line('Flags: '.implode(', ', $parsed['flags']));
        }

        $this->line('');

        return $this->confirm('Is this correct?');
    }
}
