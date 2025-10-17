<?php

namespace Modules\Ledger\Events;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BankReconciliationMatched implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public BankReconciliation $reconciliation,
        public BankReconciliationMatch $match,
        public bool $autoMatched = false,
        public ?User $user = null,
        public array $metadata = []
    ) {
        $this->metadata = array_merge($metadata, [
            'timestamp' => now()->toISOString(),
            'company_id' => $reconciliation->company_id,
            'reconciliation_id' => $reconciliation->id,
            'statement_line_description' => $match->statementLine?->description,
            'source_display_name' => $match->source_display_name,
        ]);
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn()
    {
        return [
            new PrivateChannel('company.'.$this->reconciliation->company_id.'.bank-reconciliation'),
            new PrivateChannel('bank-reconciliation.'.$this->reconciliation->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'bank.reconciliation.match.created';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'match' => [
                'id' => $this->match->id,
                'reconciliation_id' => $this->match->reconciliation_id,
                'statement_line_id' => $this->match->statement_line_id,
                'source_type' => $this->match->source_type,
                'source_id' => $this->match->source_id,
                'amount' => $this->match->formatted_amount,
                'auto_matched' => $this->match->auto_matched,
                'confidence_score' => $this->match->formatted_confidence_score,
                'confidence_level' => $this->match->confidence_level,
                'matched_at' => $this->match->matched_at->toISOString(),
            ],
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'auto_matched' => $this->autoMatched,
            'metadata' => $this->metadata,
            'impact' => [
                'variance_change' => $this->calculateVarianceImpact(),
                'percent_complete_change' => $this->calculatePercentCompleteChange(),
                'remaining_unmatched' => $this->getRemainingUnmatchedCount(),
            ],
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Calculate the variance impact of this match.
     */
    private function calculateVarianceImpact(): float
    {
        // This would be calculated based on the reconciliation's variance service
        return $this->match->amount;
    }

    /**
     * Calculate the percentage complete change.
     */
    private function calculatePercentCompleteChange(): int
    {
        $reconciliation = $this->reconciliation;
        $totalLines = $reconciliation->statement->bankStatementLines()->count();
        $matchedLines = $reconciliation->matches()->count();

        return $totalLines > 0 ? intval(($matchedLines / $totalLines) * 100) : 0;
    }

    /**
     * Get the number of remaining unmatched lines.
     */
    private function getRemainingUnmatchedCount(): int
    {
        $reconciliation = $this->reconciliation;
        $totalLines = $reconciliation->statement->bankStatementLines()->count();
        $matchedLines = $reconciliation->matches()->count();

        return max(0, $totalLines - $matchedLines);
    }
}
