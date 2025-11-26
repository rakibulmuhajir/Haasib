<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CommandPerformanceScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('is_active', true)
            ->orderBy('category')
            ->orderBy('name');
    }

    /**
     * Extend the query builder with additional methods.
     */
    public function extend(Builder $builder): void
    {
        $builder->macro('withExecutionsCount', function (Builder $builder, int $days = 30) {
            return $builder->withCount(['executions' => function ($query) use ($days) {
                $query->where('executed_at', '>=', now()->subDays($days));
            }]);
        });

        $builder->macro('withRecentExecutions', function (Builder $builder, int $limit = 5) {
            return $builder->with(['executions' => function ($query) use ($limit) {
                $query->latest('executed_at')->limit($limit);
            }]);
        });

        $builder->macro('byCategory', function (Builder $builder, string $category) {
            return $builder->where('category', $category);
        });

        $builder->macro('byPermission', function (Builder $builder, array $permissions) {
            return $builder->whereJsonContains('required_permissions', $permissions);
        });

        $builder->macro('search', function (Builder $builder, string $query) {
            return $builder->where(function ($q) use ($query) {
                $q->where('name', 'ILIKE', "%{$query}%")
                    ->orWhere('description', 'ILIKE', "%{$query}%");
            });
        });

        $builder->macro('forSuggestions', function (Builder $builder, ?string $input = null) {
            $query = $builder->select(['id', 'name', 'description', 'category', 'parameters'])
                ->with(['templates' => function ($q) {
                    $q->where('is_shared', true)->limit(3);
                }]);

            if ($input) {
                $query->where(function ($q) use ($input) {
                    $q->where('name', 'ILIKE', "%{$input}%")
                        ->orWhere('description', 'ILIKE', "%{$input}%");
                });
            }

            return $query->limit(20);
        });
    }
}
