<?php

namespace App\Http\Controllers;

use App\Facades\CompanyContext;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class PaletteSuggestionsController extends Controller
{
    /**
     * Get dynamic suggestions for command palette
     */
    public function index(Request $request): JsonResponse
    {
        $entity = $request->query('entity');
        $verb = $request->query('verb');
        $query = $request->query('q', '');

        $company = CompanyContext::getCompany();

        $suggestions = match($entity) {
            'user' => $this->getUserSuggestions($query, $verb, $company),
            'role' => $this->getRoleSuggestions($query, $verb, $company),
            'company' => $this->getCompanySuggestions($query, $verb, $company),
            default => [],
        };

        return response()->json([
            'suggestions' => $suggestions,
        ]);
    }

    /**
     * Get user suggestions
     */
    private function getUserSuggestions(string $query, string $verb, $company): array
    {
        if (!$query || strlen($query) < 2) {
            return [];
        }

        // For invite - suggest email format
        if ($verb === 'invite') {
            // Don't suggest from existing users - they're inviting new people
            return [];
        }

        // For other verbs - suggest existing users
        $users = User::query()
            ->where(function ($q) use ($query) {
                $q->where('email', 'ILIKE', "%{$query}%")
                  ->orWhere('name', 'ILIKE', "%{$query}%");
            })
            ->limit(5)
            ->get();

        return $users->map(fn($u) => [
            'value' => $u->email,
            'label' => $u->name ?? $u->email,
            'description' => $u->email,
            'icon' => '👤',
        ])->toArray();
    }

    /**
     * Get role suggestions
     */
    private function getRoleSuggestions(string $query, string $verb, $company): array
    {
        // No suggestions if no company context
        if (!$company) {
            return [];
        }

        $baseQuery = Role::where(function ($q) use ($company) {
            $q->where('company_id', $company->id)
              ->orWhereNull('company_id');  // Include global roles
        });

        if (!$query || strlen($query) < 1) {
            $roles = $baseQuery->limit(8)->get();
        } else {
            $roles = $baseQuery
                ->where('name', 'ILIKE', "%{$query}%")
                ->limit(5)
                ->get();
        }

        return $roles->map(fn($r) => [
            'value' => $r->name,
            'label' => ucfirst($r->name),
            'description' => "Role: {$r->name}",
            'icon' => '🔑',
        ])->toArray();
    }

    /**
     * Get company suggestions
     */
    private function getCompanySuggestions(string $query, string $verb, $company): array
    {
        // For create - suggest currency codes
        if ($verb === 'create') {
            if (!$query || strlen($query) < 1) {
                return $this->getCurrencySuggestions('');
            }

            // If query is 3 chars and uppercase, it's probably a currency
            if (strlen($query) === 3 && strtoupper($query) === $query) {
                return $this->getCurrencySuggestions($query);
            }

            return [];
        }

        // For view/switch/delete - suggest company slugs
        // This would need a Company model - for now return empty
        return [];
    }

    /**
     * Get currency suggestions
     */
    private function getCurrencySuggestions(string $query): array
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar'],
            ['code' => 'EUR', 'name' => 'Euro'],
            ['code' => 'GBP', 'name' => 'British Pound'],
            ['code' => 'JPY', 'name' => 'Japanese Yen'],
            ['code' => 'CAD', 'name' => 'Canadian Dollar'],
            ['code' => 'AUD', 'name' => 'Australian Dollar'],
            ['code' => 'CHF', 'name' => 'Swiss Franc'],
            ['code' => 'CNY', 'name' => 'Chinese Yuan'],
        ];

        if ($query) {
            $currencies = array_filter($currencies, function($c) use ($query) {
                return stripos($c['code'], $query) === 0;
            });
        }

        return array_values(array_map(fn($c) => [
            'value' => $c['code'],
            'label' => $c['code'],
            'description' => $c['name'],
            'icon' => '💱',
        ], $currencies));
    }
}
