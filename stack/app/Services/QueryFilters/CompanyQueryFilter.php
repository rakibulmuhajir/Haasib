<?php

namespace App\Services\QueryFilters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CompanyQueryFilter
{
    public function __construct(
        private Request $request
    ) {}

    public function apply(Builder $query): Builder
    {
        // Apply all filters
        $query = $this->applySearchFilter($query);
        $query = $this->applyIndustryFilter($query);
        $query = $this->applyCountryFilter($query);
        $query = $this->applyStatusFilter($query);
        $query = $this->applyCurrencyFilter($query);
        $query = $this->applyDateRangeFilter($query);
        $query = $this->applyUserFilter($query);
        $query = $this->applySorting($query);

        return $query;
    }

    private function applySearchFilter(Builder $query): Builder
    {
        $search = $this->request->get('search');
        
        if (! $search) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'ILIKE', "%{$search}%")
              ->orWhere('slug', 'ILIKE', "%{$search}%")
              ->orWhere('industry', 'ILIKE', "%{$search}%");
        });
    }

    private function applyIndustryFilter(Builder $query): Builder
    {
        $industry = $this->request->get('industry');
        
        if (! $industry) {
            return $query;
        }

        return $query->where('industry', $industry);
    }

    private function applyCountryFilter(Builder $query): Builder
    {
        $country = $this->request->get('country');
        
        if (! $country) {
            return $query;
        }

        return $query->where('country', $country);
    }

    private function applyStatusFilter(Builder $query): Builder
    {
        $isActive = $this->request->get('is_active');
        
        if ($isActive === null) {
            return $query;
        }

        return $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
    }

    private function applyCurrencyFilter(Builder $query): Builder
    {
        $currency = $this->request->get('currency');
        
        if (! $currency) {
            return $query;
        }

        return $query->where('base_currency', $currency);
    }

    private function applyDateRangeFilter(Builder $query): Builder
    {
        $dateFrom = $this->request->get('created_from');
        $dateTo = $this->request->get('created_to');

        if ($dateFrom) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        return $query;
    }

    private function applyUserFilter(Builder $query): Builder
    {
        $userId = $this->request->get('user_id');
        
        if (! $userId) {
            return $query;
        }

        return $query->whereHas('users', function (Builder $q) use ($userId) {
            $q->where('users.id', $userId)
              ->where('company_user.is_active', true);
        });
    }

    private function applySorting(Builder $query): Builder
    {
        $sortField = $this->request->get('sort', 'name');
        $sortDirection = $this->request->get('direction', 'asc');

        // Validate sort field
        $allowedSortFields = [
            'name',
            'slug', 
            'industry',
            'country',
            'base_currency',
            'is_active',
            'created_at',
            'updated_at',
            'users_count',
        ];

        if (! in_array($sortField, $allowedSortFields)) {
            $sortField = 'name';
        }

        // Validate direction
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) ? strtolower($sortDirection) : 'asc';

        // Apply sorting
        if ($sortField === 'users_count') {
            // Special handling for user count sorting
            return $query->withCount(['users' => function (Builder $q) {
                $q->where('company_user.is_active', true);
            }])->orderBy('users_count', $sortDirection);
        }

        return $query->orderBy($sortField, $sortDirection);
    }

    /**
     * Get available filter options for API documentation
     */
    public function getAvailableFilters(): array
    {
        return [
            'search' => [
                'description' => 'Search in company name, slug, and industry',
                'type' => 'string',
                'example' => 'Acme Corp',
            ],
            'industry' => [
                'description' => 'Filter by industry',
                'type' => 'string',
                'options' => ['hospitality', 'retail', 'professional_services', 'technology', 'healthcare', 'education', 'manufacturing', 'other'],
                'example' => 'technology',
            ],
            'country' => [
                'description' => 'Filter by country code',
                'type' => 'string',
                'length' => 2,
                'example' => 'US',
            ],
            'is_active' => [
                'description' => 'Filter by active status',
                'type' => 'boolean',
                'options' => [true, false],
                'example' => true,
            ],
            'currency' => [
                'description' => 'Filter by base currency',
                'type' => 'string',
                'length' => 3,
                'example' => 'USD',
            ],
            'created_from' => [
                'description' => 'Filter companies created from this date',
                'type' => 'date',
                'format' => 'YYYY-MM-DD',
                'example' => '2024-01-01',
            ],
            'created_to' => [
                'description' => 'Filter companies created to this date',
                'type' => 'date',
                'format' => 'YYYY-MM-DD',
                'example' => '2024-12-31',
            ],
            'user_id' => [
                'description' => 'Filter companies that have a specific user',
                'type' => 'uuid',
                'example' => '123e4567-e89b-12d3-a456-426614174000',
            ],
            'sort' => [
                'description' => 'Sort field',
                'type' => 'string',
                'options' => ['name', 'slug', 'industry', 'country', 'base_currency', 'is_active', 'created_at', 'updated_at', 'users_count'],
                'default' => 'name',
                'example' => 'created_at',
            ],
            'direction' => [
                'description' => 'Sort direction',
                'type' => 'string',
                'options' => ['asc', 'desc'],
                'default' => 'asc',
                'example' => 'desc',
            ],
        ];
    }
}