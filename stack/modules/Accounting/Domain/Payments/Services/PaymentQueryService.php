<?php

namespace Modules\Accounting\Domain\Payments\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentQueryService
{
    /**
     * Get audit trail for a specific payment.
     */
    public function getPaymentAuditTrail(string $paymentId, array $filters = []): array
    {
        $query = DB::table('invoicing.payment_audit_log')
            ->where('payment_id', $paymentId)
            ->orderBy('timestamp', 'desc');

        // Apply filters
        if (!empty($filters['start_date'])) {
            $query->where('timestamp', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('timestamp', '<=', $filters['end_date'] . ' 23:59:59');
        }

        if (!empty($filters['actions'])) {
            $actions = is_array($filters['actions']) ? $filters['actions'] : [$filters['actions']];
            $query->whereIn('action', $actions);
        }

        if (!empty($filters['actor_types'])) {
            $actorTypes = is_array($filters['actor_types']) ? $filters['actor_types'] : [$filters['actor_types']];
            $query->whereIn('actor_type', $actorTypes);
        }

        $entries = $query->get()->map(function ($entry) {
            return [
                'id' => $entry->id,
                'action' => $entry->action,
                'actor_id' => $entry->actor_id,
                'actor_type' => $entry->actor_type,
                'timestamp' => Carbon::parse($entry->timestamp)->toISOString(),
                'metadata' => json_decode($entry->metadata, true) ?? [],
                'company_id' => $entry->company_id,
            ];
        })->toArray();

        return $entries;
    }

    /**
     * Get audit trail for all payments in a company.
     */
    public function getCompanyAuditTrail(array $filters = [], array $pagination = []): array
    {
        $query = DB::table('invoicing.payment_audit_log as audit')
            ->select([
                'audit.id',
                'audit.payment_id',
                'audit.action',
                'audit.actor_id',
                'audit.actor_type',
                'audit.timestamp',
                'audit.metadata',
                'audit.company_id',
                'p.payment_number',
                'p.payment_method',
                'p.amount',
                'p.currency_id',
                'p.entity_id',
                'c.name as entity_name',
            ])
            ->leftJoin('acct.payments as p', 'audit.payment_id', '=', 'p.payment_id')
            ->leftJoin('hrm.customers as c', 'p.entity_id', '=', 'c.customer_id')
            ->orderBy($pagination['sort_by'] ?? 'timestamp', $pagination['sort_direction'] ?? 'desc');

        // Apply filters
        if (!empty($filters['start_date'])) {
            $query->where('audit.timestamp', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('audit.timestamp', '<=', $filters['end_date'] . ' 23:59:59');
        }

        if (!empty($filters['actions'])) {
            $actions = is_array($filters['actions']) ? $filters['actions'] : [$filters['actions']];
            $query->whereIn('audit.action', $actions);
        }

        if (!empty($filters['actor_types'])) {
            $actorTypes = is_array($filters['actor_types']) ? $filters['actor_types'] : [$filters['actor_types']];
            $query->whereIn('audit.actor_type', $actorTypes);
        }

        if (!empty($filters['payment_methods'])) {
            $methods = is_array($filters['payment_methods']) ? $filters['payment_methods'] : [$filters['payment_methods']];
            $query->whereIn('p.payment_method', $methods);
        }

        if (!empty($filters['payment_statuses'])) {
            $statuses = is_array($filters['payment_statuses']) ? $filters['payment_statuses'] : [$filters['payment_statuses']];
            $query->whereIn('p.status', $statuses);
        }

        if (!empty($filters['entity_id'])) {
            $query->where('p.entity_id', $filters['entity_id']);
        }

        if (!empty($filters['min_amount'])) {
            $query->where('p.amount', '>=', $filters['min_amount']);
        }

        if (!empty($filters['max_amount'])) {
            $query->where('p.amount', '<=', $filters['max_amount']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('p.payment_number', 'ilike', "%{$search}%")
                  ->orWhere('c.name', 'ilike', "%{$search}%")
                  ->orWhere('audit.action', 'ilike', "%{$search}%");
            });
        }

        // Get total count for pagination
        $total = $query->count();

        // Apply pagination
        $page = max(1, $pagination['page'] ?? 1);
        $limit = min($pagination['limit'] ?? 50, 100);
        $offset = ($page - 1) * $limit;

        $results = $query->offset($offset)->limit($limit)->get()->map(function ($entry) {
            return [
                'id' => $entry->id,
                'payment_id' => $entry->payment_id,
                'payment_number' => $entry->payment_number,
                'action' => $entry->action,
                'actor_id' => $entry->actor_id,
                'actor_type' => $entry->actor_type,
                'timestamp' => Carbon::parse($entry->timestamp)->toISOString(),
                'metadata' => json_decode($entry->metadata, true) ?? [],
                'payment_details' => [
                    'payment_method' => $entry->payment_method,
                    'amount' => $entry->amount,
                    'currency_id' => $entry->currency_id,
                    'entity_id' => $entry->entity_id,
                    'entity_name' => $entry->entity_name,
                ],
                'company_id' => $entry->company_id,
            ];
        })->toArray();

        return [
            'data' => $results,
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'last_page' => ceil($total / $limit),
        ];
    }

    /**
     * Get bank reconciliation audit data.
     */
    public function getBankReconciliationAudit(array $filters = []): array
    {
        $query = DB::table('acct.payments as p')
            ->select([
                'p.payment_id',
                'p.payment_number',
                'p.payment_method',
                'p.amount',
                'p.payment_date',
                'p.status',
                'p.reconciled',
                'p.reconciled_date',
                'p.entity_id',
                'c.name as entity_name',
                'audit.timestamp as last_audit_timestamp',
                'audit.action as last_audit_action',
            ])
            ->leftJoin('hrm.customers as c', 'p.entity_id', '=', 'c.customer_id')
            ->leftJoin('invoicing.payment_audit_log as audit', function ($join) {
                $join->on('p.payment_id', '=', 'audit.payment_id')
                     ->whereRaw('audit.timestamp = (
                         SELECT MAX(timestamp) 
                         FROM invoicing.payment_audit_log 
                         WHERE payment_id = p.payment_id
                     )');
            })
            ->orderBy('p.payment_date', 'desc')
            ->orderBy('p.payment_number', 'desc');

        // Apply filters
        if (!empty($filters['start_date'])) {
            $query->where('p.payment_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('p.payment_date', '<=', $filters['end_date']);
        }

        if (!empty($filters['payment_number'])) {
            $query->where('p.payment_number', 'ilike', '%' . $filters['payment_number'] . '%');
        }

        if ($filters['reconciled_only'] === 'true') {
            $query->where('p.reconciled', true);
        }

        if ($filters['unreconciled_only'] === 'true') {
            $query->where('p.reconciled', false);
        }

        $results = $query->get()->map(function ($payment) {
            return [
                'payment_id' => $payment->payment_id,
                'payment_number' => $payment->payment_number,
                'payment_method' => $payment->payment_method,
                'amount' => $payment->amount,
                'payment_date' => $payment->payment_date,
                'status' => $payment->status,
                'reconciled' => $payment->reconciled,
                'reconciled_date' => $payment->reconciled_date ? Carbon::parse($payment->reconciled_date)->toISOString() : null,
                'entity' => [
                    'id' => $payment->entity_id,
                    'name' => $payment->entity_name,
                ],
                'last_audit' => [
                    'timestamp' => $payment->last_audit_timestamp ? Carbon::parse($payment->last_audit_timestamp)->toISOString() : null,
                    'action' => $payment->last_audit_action,
                ],
            ];
        })->toArray();

        return $results;
    }

    /**
     * Get audit metrics and statistics.
     */
    public function getAuditMetrics(array $dateRange): array
    {
        $startDate = $dateRange['start_date'];
        $endDate = $dateRange['end_date'] . ' 23:59:59';

        // Get audit action counts
        $actionCounts = DB::table('invoicing.payment_audit_log')
            ->select('action', DB::raw('count(*) as count'))
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->groupBy('action')
            ->orderBy('count', 'desc')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->action => (int)$item->count];
            })
            ->toArray();

        // Get actor type distribution
        $actorTypeCounts = DB::table('invoicing.payment_audit_log')
            ->select('actor_type', DB::raw('count(*) as count'))
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->groupBy('actor_type')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->actor_type => (int)$item->count];
            })
            ->toArray();

        // Get daily activity counts
        $dailyActivity = DB::table('invoicing.payment_audit_log')
            ->select(
                DB::raw('DATE(timestamp) as date'),
                DB::raw('count(*) as count')
            )
            ->whereBetween('timestamp', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'count' => (int)$item->count,
                ];
            })
            ->toArray();

        // Get payment method distribution
        $paymentMethodCounts = DB::table('invoicing.payment_audit_log as audit')
            ->select('p.payment_method', DB::raw('count(*) as count'))
            ->leftJoin('acct.payments as p', 'audit.payment_id', '=', 'p.payment_id')
            ->whereBetween('audit.timestamp', [$startDate, $endDate])
            ->whereNotNull('p.payment_method')
            ->groupBy('p.payment_method')
            ->orderBy('count', 'desc')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->payment_method => (int)$item->count];
            })
            ->toArray();

        // Get reconciliation metrics
        $reconciliationMetrics = [
            'total_payments' => DB::table('acct.payments')
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->count(),
            'reconciled_payments' => DB::table('acct.payments')
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->where('reconciled', true)
                ->count(),
            'unreconciled_payments' => DB::table('acct.payments')
                ->whereBetween('payment_date', [$startDate, $endDate])
                ->where('reconciled', false)
                ->count(),
        ];

        $reconciliationMetrics['reconciliation_rate'] = $reconciliationMetrics['total_payments'] > 0
            ? round(($reconciliationMetrics['reconciled_payments'] / $reconciliationMetrics['total_payments']) * 100, 2)
            : 0;

        return [
            'action_counts' => $actionCounts,
            'actor_type_distribution' => $actorTypeCounts,
            'daily_activity' => $dailyActivity,
            'payment_method_distribution' => $paymentMethodCounts,
            'reconciliation_metrics' => $reconciliationMetrics,
            'total_audit_events' => array_sum($actionCounts),
        ];
    }
}