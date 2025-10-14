<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $companyUser = $this->whenLoaded('users') 
            ? $this->users->where('id', $user?->id)->first()
            : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'industry' => $this->industry,
            'country' => $this->country,
            'base_currency' => $this->base_currency,
            'currency' => $this->currency ?? $this->base_currency,
            'timezone' => $this->timezone,
            'language' => $this->language ?? 'en',
            'locale' => $this->locale ?? 'en_US',
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Relationships
            'fiscal_year' => $this->whenLoaded('fiscalYear', function () {
                return $this->fiscalYear ? [
                    'id' => $this->fiscalYear->id,
                    'name' => $this->fiscalYear->name,
                    'start_date' => $this->fiscalYear->start_date,
                    'end_date' => $this->fiscalYear->end_date,
                    'is_active' => $this->fiscalYear->is_active,
                ] : null;
            }),

            'user_role' => $this->when($companyUser, function () use ($companyUser) {
                return [
                    'role' => $companyUser->role,
                    'is_active' => $companyUser->is_active,
                    'joined_at' => $companyUser->created_at,
                ];
            }),

            'users_count' => $this->when(isset($this->users_count), $this->users_count),
            'active_users_count' => $this->when(isset($this->active_users_count), $this->active_users_count),

            // Settings (filtered for security)
            'settings' => $this->when($this->settings && $this->userCanViewSettings($request), function () {
                return array_filter($this->settings, function ($key) {
                    // Only expose safe settings
                    $safeKeys = [
                        'timezone',
                        'date_format',
                        'number_format',
                        'currency_decimal_places',
                        'enabled_modules',
                    ];
                    return in_array($key, $safeKeys);
                }, ARRAY_FILTER_USE_KEY);
            }),

            // Navigation and permissions
            'permissions' => $this->when($user, function () use ($user) {
                $permissionService = app(\App\Services\CompanyPermissionService::class);
                return $permissionService->getUserPermissionsInCompany($user, $this->resource);
            }),

            // Metadata
            '_links' => [
                'self' => route('companies.show', $this->id),
                'users' => route('companies.users.index', $this->id),
                'invitations' => route('companies.invitations.index', $this->id),
                'modules' => route('companies.modules.index', $this->id),
            ],
        ];
    }

    private function userCanViewSettings(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        // Super admins can view all settings
        if (in_array($user->system_role, ['system_owner', 'super_admin'])) {
            return true;
        }

        // Company owners and admins can view settings
        $companyUser = $this->users->where('id', $user->id)->first();
        return $companyUser && in_array($companyUser->role, ['owner', 'admin']);
    }

    /**
     * Get additional data that should be returned with the resource array.
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'filtered_fields' => [
                    'settings' => 'Only safe settings are exposed',
                ],
            ],
        ];
    }

    /**
     * Customize the response for a collection.
     */
    public static function collection($resource)
    {
        $collection = parent::collection($resource);
        
        // Add collection metadata
        $collection->additional['meta'] = [
            'total' => $resource->count(),
            'filtered' => true,
            'company_context' => request()->attributes->get('company')?->id,
        ];

        return $collection;
    }
}