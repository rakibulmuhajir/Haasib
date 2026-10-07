<?php

namespace App\Modules\Accounting\Services;

use App\Constants\Permissions;
use App\Models\User;

/** Graph construction beside statement calculations, with evidence from their own queries. */
class StatementValueTrail
{
    public const EVIDENCE_SQL = "json_agg(json_build_object('id', je.id, 'transaction_id', t.id, 'date', t.transaction_date, 'debit', je.debit_amount, 'credit', je.credit_amount)) AS ledger_evidence";

    public function available(?User $user): bool
    {
        return $user && $user->showsValueTrails() && ($user->isGodMode() || $user->hasCompanyPermission(Permissions::REPORT_VIEW));
    }

    public function present(callable $build, User $user, string $slug): ?array
    {
        if (! $this->available($user)) {
            return null;
        }
        try {
            $graph = $build();
            if (! $graph) {
                return null;
            }
            if (isset($graph['error'])) {
                return $graph;
            }
            foreach ($graph['nodes'] as &$node) {
                if (isset($node['source']['document_link'])) {
                    $source = $node['source'];
                    $permission = match (explode('/', $source['document_link'])[0]) {
                        'invoices' => Permissions::INVOICE_VIEW,
                        'payments', 'bill-payments' => Permissions::PAYMENT_VIEW,
                        'bills' => Permissions::BILL_VIEW,
                        'credit-notes' => Permissions::CREDIT_NOTE_VIEW,
                        'vendor-credits' => Permissions::VENDOR_CREDIT_VIEW,
                        'payslips' => Permissions::PAYSLIP_VIEW,
                        default => null,
                    };
                    $node['source'] = $permission && ($user->isGodMode() || $user->hasCompanyPermission($permission))
                        ? ['label' => $source['label'], 'date' => $source['date'], 'recorded_at' => $source['recorded_at'], 'href' => '/'.$slug.'/'.$source['document_link']]
                        : ['restricted' => true];
                }
                if (isset($node['source']['transaction_id'])) {
                    $id = $node['source']['transaction_id'];
                    $node['source'] = $user->isGodMode() || $user->hasCompanyPermission(Permissions::JOURNAL_VIEW)
                        ? ['label' => 'Journal entry', 'date' => $node['source']['date'], 'href' => '/'.$slug.'/journals/'.$id]
                        : ['restricted' => true];
                }
            }

            return app(\App\Services\ValueTrailBatch::class)->present($graph);
        } catch (\Throwable $e) {
            report($e);

            return ['error' => 'Statement evidence could not be loaded. Please try again.'];
        }
    }

    public function node(array &$graph, string $id, string $label, float $value, array $children = [], ?string $formula = null, ?array $source = null): string
    {
        $graph['nodes'][$id] = ['id' => $id, 'label' => $label, 'value' => $value, 'unit' => 'money',
            'children' => $children, 'formula' => $formula, 'source' => $source, 'estimated' => false,
            'explanation' => 'Uses the signed contributions and filters of this statement.'];
        $graph['roots'][$id] = $id;

        return $id;
    }

    public function account(array &$graph, object $row, bool $debitNormal): string
    {
        $children = [];
        foreach (json_decode($row->ledger_evidence ?? '[]', true) as $line) {
            if (empty($line['id'])) {
                continue;
            }
            $amount = $debitNormal ? (float) $line['debit'] - (float) $line['credit'] : (float) $line['credit'] - (float) $line['debit'];
            $children[] = $this->node($graph, 'entry:'.$line['id'], 'Entry · '.$line['date'], $amount,
                formula: $debitNormal ? 'Debit − credit' : 'Credit − debit', source: ['transaction_id' => $line['transaction_id'], 'date' => $line['date']]);
        }

        return $this->node($graph, 'account:'.$row->id, $row->name,
            round($debitNormal ? (float) $row->debit - (float) $row->credit : (float) $row->credit - (float) $row->debit, 2), $children, 'Sum of signed ledger entries');
    }

    public function party(array $rows, string $prefix, string $name, ?string $from, ?string $to, bool $customer, float $opening, float $closing): array
    {
        $graph = ['nodes' => [], 'roots' => [], 'context' => ['start_date' => $from, 'end_date' => $to]];
        $prior = $movements = [];
        foreach ($rows as $index => $row) {
            if ($to && $row['date'] && $row['date'] > $to) {
                continue;
            }
            $value = $customer ? $row['debit'] - $row['credit'] : $row['credit'] - $row['debit'];
            $id = $this->node($graph, $prefix.':movement:'.$index, ucfirst(str_replace('_', ' ', $row['type'])).' · '.$row['date'], round($value, 2),
                formula: $customer ? 'Invoiced − received or credited' : 'Billed − paid or credited',
                source: $row['link'] ? ['document_link' => $row['link'], 'label' => $row['reference'], 'date' => $row['date'], 'recorded_at' => $row['created_at'] ?? null] : null);
            if ($from && $row['date'] && $row['date'] < $from) {
                $prior[] = $id;
            } else {
                $movements[] = $id;
            }
        }
        $this->node($graph, $prefix.':opening', 'Opening · '.$name, $opening, $prior, 'Sum of prior signed documents');
        $this->node($graph, $prefix.':closing', 'Closing · '.$name, $closing, [$prefix.':opening', ...$movements], 'Opening + signed documents');
        $graph['roots']['statement:opening'] = $prefix.':opening';
        $graph['roots']['statement:closing'] = $prefix.':closing';

        return $graph;
    }
}
