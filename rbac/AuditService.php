<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Record an audit log entry.
     *
     * @param string $action Action name (e.g., 'invoice.approved', 'role.assigned')
     * @param Model|null $subject The model being acted upon
     * @param array|null $oldValues Previous values (for updates)
     * @param array|null $newValues New values
     * @param array $meta Additional metadata
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $meta = []
    ): Audit {
        $company = app(CurrentCompany::class)->get();

        return Audit::create([
            'user_id' => auth()->id(),
            'company_id' => $company?->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'meta' => $meta ?: null,
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Shorthand for recording without subject.
     */
    public static function log(string $action, array $data = []): Audit
    {
        return self::record($action, null, null, $data);
    }

    /**
     * Record a model creation.
     */
    public static function created(Model $model, string $action = null): Audit
    {
        $action = $action ?? class_basename($model) . '.created';

        return self::record($action, $model, null, $model->toArray());
    }

    /**
     * Record a model update.
     */
    public static function updated(Model $model, array $oldValues, string $action = null): Audit
    {
        $action = $action ?? class_basename($model) . '.updated';

        return self::record($action, $model, $oldValues, $model->toArray());
    }

    /**
     * Record a model deletion.
     */
    public static function deleted(Model $model, string $action = null): Audit
    {
        $action = $action ?? class_basename($model) . '.deleted';

        return self::record($action, $model, $model->toArray(), null);
    }
}
