<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\ChangeLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class TravelChangeLogger
{
    public function log(Request $request, Model $entity, string $entityType, string $action, array $oldValues, array $newValues, ?string $reason = null, array $metadata = []): ?ChangeLog
    {
        $changes = collect($newValues)
            ->filter(fn ($value, $key) => ($oldValues[$key] ?? null) != $value)
            ->all();

        if ($changes === []) {
            return null;
        }

        return ChangeLog::create([
            'company_id' => $entity->getAttribute('company_id'),
            'user_id' => $request->user()?->id,
            'entity_type' => $entityType,
            'entity_id' => $entity->getKey(),
            'action' => $action,
            'reason' => $reason,
            'old_values' => collect($oldValues)->only(array_keys($changes))->all(),
            'new_values' => $changes,
            'metadata' => [
                ...$metadata,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
        ]);
    }
}
