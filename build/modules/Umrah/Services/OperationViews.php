<?php

namespace App\Modules\Umrah\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class OperationViews
{
    public function listing(string $companyId, string $userId): array
    {
        return DB::table('umrah.operation_views')->where('company_id', $companyId)->where('user_id', $userId)
            ->orderBy('name')->get(['id', 'name', 'filters'])->map(fn ($row) => [
                'id' => $row->id, 'name' => $row->name, 'filters' => json_decode($row->filters, true),
            ])->all();
    }

    public function persist(string $companyId, string $userId, array $data, ?string $deleteId): void
    {
        $query = DB::table('umrah.operation_views')->where('company_id', $companyId)->where('user_id', $userId);
        if ($deleteId !== null) {
            if (! Str::isUuid($deleteId) || ! $query->where('id', $deleteId)->delete()) {
                throw ValidationException::withMessages(['name' => 'This saved view is no longer available.']);
            }

            return;
        }
        $filters = array_intersect_key($data, array_flip(['period', 'event_type', 'readiness', 'agent_id']));
        if ($data['period'] === 'custom') {
            $filters['start'] = $data['start'];
            $filters['end'] = $data['end'];
        }
        DB::table('umrah.operation_views')->upsert([[
            'id' => (string) Str::uuid(), 'company_id' => $companyId, 'user_id' => $userId,
            'name' => trim($data['name']), 'filters' => json_encode($filters, JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]], ['company_id', 'user_id', 'name'], ['filters', 'updated_at']);
    }
}
