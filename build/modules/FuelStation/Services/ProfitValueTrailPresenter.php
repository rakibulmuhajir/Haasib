<?php

namespace App\Modules\FuelStation\Services;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Resolve original-record links only when the caller may view that document. */
class ProfitValueTrailPresenter
{
    public function present(array $trail, Company $company, User $user): array
    {
        $routes = [
            'daily_close' => [Permissions::DAILY_CLOSE_VIEW, 'fuel/daily-close'],
            'bill' => [Permissions::BILL_VIEW, 'bills'],
            'invoice' => [Permissions::INVOICE_VIEW, 'invoices'],
            'journal' => [Permissions::JOURNAL_VIEW, 'journals'],
            'tank_reading' => [Permissions::TANK_READING_VIEW, 'fuel/tank-readings'],
        ];
        $allowed = [];
        foreach ($routes as $kind => [$permission]) {
            $allowed[$kind] = $user->isGodMode() || $user->hasCompanyPermission($permission);
        }
        // Fetch creator evidence only for authorized source documents, once per
        // document type. Do not add identities to other reports' shared DTOs.
        $sourceIds = [];
        foreach ($trail['nodes'] as $node) {
            $source = $node['source'];
            if ($source && ($allowed[$source['kind']] ?? false)) {
                $sourceIds[$source['kind']][] = $source['id'];
            }
        }
        $records = [];
        foreach ($sourceIds as $kind => $ids) {
            $table = match ($kind) {
                'bill' => 'acct.bills',
                'invoice' => 'acct.invoices',
                'tank_reading' => 'fuel.tank_readings',
                default => 'acct.transactions',
            };
            $records[$kind] = DB::table($table)->where('company_id', $company->id)
                ->whereIn('id', array_unique($ids))
                ->when($kind !== 'tank_reading', fn ($query) => $query->whereNull('deleted_at'))
                ->get(['id', $kind === 'tank_reading' ? 'recorded_by_user_id as created_by_user_id' : 'created_by_user_id', 'created_at'])->keyBy('id')->all();
        }
        $actorIds = [];
        foreach ($trail['nodes'] as &$node) {
            $source = $node['source'];
            if ($source && ($allowed[$source['kind']] ?? false)) {
                $record = $records[$source['kind']][$source['id']] ?? null;
                if (! $record) {
                    continue;
                }
                $source['recorded_by_id'] = $source['recorded_by_id'] ?? $record?->created_by_user_id;
                $source['recorded_at'] = $source['recorded_at'] ?? $record?->created_at;
                if ($source['recorded_by_id']) {
                    $actorIds[] = $source['recorded_by_id'];
                }
                $node['source'] = $source;
            }
        }
        unset($node);
        $actors = $actorIds === [] ? [] : DB::table('auth.users')->whereIn('id', array_unique($actorIds))->pluck('name', 'id')->all();

        foreach ($trail['nodes'] as &$node) {
            $source = $node['source'];
            if (! $source) {
                continue;
            }
            $kind = $source['kind'];
            if (! ($allowed[$kind] ?? false)) {
                $node['source'] = ['restricted' => true];

                continue;
            }
            if (! isset($records[$kind][$source['id']])) {
                $node['source'] = ['unavailable' => true];

                continue;
            }
            $source['href'] = '/'.$company->slug.'/'.$routes[$kind][1].'/'.$source['id'];
            $source['recorded_by'] = $actors[$source['recorded_by_id'] ?? ''] ?? null;
            unset($source['recorded_by_id']);
            $node['source'] = $source;
        }
        unset($node);

        return app(\App\Services\ValueTrailBatch::class)->present($trail);
    }
}
