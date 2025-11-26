<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Services\ContextService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    public function __construct(
        private ContextService $contextService
    ) {}

    /**
     * Switch to a different company context (copied from stack working pattern).
     */
    public function switch(Company $company, Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return $this->handleResponse($request, false, 'User not authenticated', 401);
        }

        try {
            $success = $this->contextService->setCurrentCompany($user, $company);

            if ($success) {
                Log::info('Company switched successfully', [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                ]);

                return $this->handleResponse($request, true, "Switched to {$company->name}");
            } else {
                Log::warning('Company switch failed - access denied', [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                    'company_name' => $company->name,
                ]);

                return $this->handleResponse($request, false, 'Access denied to this company', 403);
            }

        } catch (\Exception $e) {
            Log::error('Company switch system error', [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'error' => $e->getMessage(),
            ]);

            return $this->handleResponse($request, false, 'An error occurred while switching companies', 500);
        }
    }

    /**
     * Set first available company as current (copied from stack pattern).
     */
    public function setFirstCompany(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return $this->handleResponse($request, false, 'User not authenticated', 401);
        }

        try {
            $firstCompany = $user->companies()->first();

            if (!$firstCompany) {
                return $this->handleResponse($request, false, 'No companies available', 404);
            }

            $success = $this->contextService->setCurrentCompany($user, $firstCompany);

            if ($success) {
                Log::info('Set first company successfully', [
                    'user_id' => $user->id,
                    'company_id' => $firstCompany->id,
                    'company_name' => $firstCompany->name,
                ]);

                return $this->handleResponse($request, true, "Set company to {$firstCompany->name}");
            } else {
                return $this->handleResponse($request, false, 'Failed to set company context', 500);
            }

        } catch (\Exception $e) {
            Log::error('Set first company error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->handleResponse($request, false, 'An error occurred while setting company', 500);
        }
    }

    /**
     * Clear current company context (copied from stack pattern).
     */
    public function clearContext(Request $request): RedirectResponse|JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return $this->handleResponse($request, false, 'User not authenticated', 401);
        }

        try {
            $this->contextService->clearCurrentCompany($user);

            Log::info('Company context cleared successfully', [
                'user_id' => $user->id,
            ]);

            return $this->handleResponse($request, true, 'Company context cleared');

        } catch (\Exception $e) {
            Log::error('Clear company context error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return $this->handleResponse($request, false, 'An error occurred while clearing context', 500);
        }
    }

    /**
     * Get current company context status.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not authenticated.',
            ], 401);
        }

        try {
            $currentCompany = $this->contextService->getCurrentCompany($user);
            $userCompanies = $this->contextService->getActiveCompanies($user);
            $metadata = $this->contextService->getContextMetadata($user);

            Log::info('[CompanyStatus] payload', [
                'user_id' => $user->id,
                'current_company_id' => $currentCompany?->id,
                'current_company_name' => $currentCompany?->name,
                'session_current_company_id' => $request->session()->get('current_company_id'),
                'session_active_company_id' => $request->session()->get('active_company_id'),
                'user_companies_count' => $userCompanies->count(),
                'user_company_ids' => $userCompanies->pluck('id')->all(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    // Keep original shape for any consumers
                    'current_company' => $currentCompany ? [
                        'id' => $currentCompany->id,
                        'name' => $currentCompany->name,
                        'slug' => $currentCompany->slug ?? null,
                    ] : null,
                    'user_companies' => $userCompanies->map(function ($company) {
                        return [
                            'id' => $company->id,
                            'name' => $company->name,
                            'slug' => $company->slug ?? null,
                        ];
                    })->toArray(),
                    'metadata' => $metadata,
                    // Add aliases expected by the debugger component
                    'active_company' => $currentCompany ? [
                        'id' => $currentCompany->id,
                        'name' => $currentCompany->name,
                        'slug' => $currentCompany->slug ?? null,
                        'industry' => $currentCompany->industry ?? null,
                        'country' => $currentCompany->country ?? null,
                        'base_currency' => $currentCompany->base_currency ?? null,
                        'role' => $currentCompany->pivot?->role ?? null,
                    ] : null,
                    'debug_info' => [
                        'session_current_company_id' => $request->session()->get('current_company_id'),
                        'session_active_company_id' => $request->session()->get('active_company_id'),
                        'user_company_ids' => $userCompanies->pluck('id')->all(),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Company status error', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve company status.',
            ], 500);
        }
    }

    /**
     * Handle response based on request type (JSON or redirect).
     */
    private function handleResponse(
        Request $request, 
        bool $success, 
        string $message, 
        int $statusCode = 200
    ): JsonResponse|RedirectResponse {
        
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => $success,
                'message' => $message,
            ], $statusCode);
        }

        // For web requests, redirect back with flash message
        $flashType = $success ? 'success' : 'error';
        return redirect()->back()->with($flashType, $message);
    }
}
