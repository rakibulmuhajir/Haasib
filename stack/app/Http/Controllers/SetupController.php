<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Services\SetupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SetupController extends Controller
{
    public function __construct(private readonly SetupService $setupService) {}

    /**
     * Initialize the platform with an initial user and company.
     */
    public function initialize(Request $request): JsonResponse
    {
        $request->validate([
            'confirm_reset' => 'required|boolean',
            'create_demo_data' => 'sometimes|boolean',
            'user_data' => 'required|array',
            'user_data.name' => 'required|string|min:3|max:255',
            'user_data.email' => 'required|email',
            'user_data.username' => 'required|string|min:3|max:255',
            'user_data.password' => 'required|string|min:8',
            'user_data.system_role' => 'sometimes|in:system_owner,company_owner,accountant,member',
            'companies_data' => 'required|array|min:1',
            'companies_data.*.name' => 'required|string|min:3|max:255',
            'companies_data.*.industry' => 'required|string|in:technology,hospitality,retail,professional_services,other',
            'companies_data.*.base_currency' => 'required|string|size:3',
        ]);

        if (!$request->confirm_reset) {
            return response()->json([
                'success' => false,
                'message' => 'System reset confirmation is required',
            ], 400);
        }

        if ($this->setupService->isInitialized()) {
            return response()->json([
                'success' => false,
                'message' => 'System is already initialized',
            ], 400);
        }

        try {
            $userData = $request->user_data;
            $companiesData = $request->companies_data;

            // If demo data is requested, add sample companies
            if ($request->create_demo_data) {
                $companiesData = array_merge($companiesData, $this->getDemoCompanies());
            }

            $result = $this->setupService->initialize($userData, $companiesData);

            return response()->json([
                'success' => true,
                'message' => 'System initialized successfully',
                'data' => $result,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize system: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the current setup status of the platform.
     */
    public function status(): JsonResponse
    {
        $status = $this->setupService->getStatus();
        
        return response()->json([
            'is_setup' => $status['initialized'],
            'has_companies' => $status['requirements']['companies'] > 0,
            'has_users' => $status['requirements']['system_owner'],
            'modules_enabled' => $this->getEnabledModules(),
        ]);
    }

    /**
     * Get list of enabled module names.
     */
    private function getEnabledModules(): array
    {
        $status = $this->setupService->getStatus();
        
        // If system has no companies or users, return empty modules
        if (!$status['requirements']['system_owner'] && $status['requirements']['companies'] === 0) {
            return [];
        }
        
        // Return active modules that exist in the system
        return Module::where('is_active', true)
            ->pluck('name')
            ->toArray();
    }

    /**
     * Get demo company data for testing.
     */
    private function getDemoCompanies(): array
    {
        return [
            [
                'name' => 'Grand Hotel',
                'industry' => 'hospitality',
                'base_currency' => 'USD',
                'fiscal_year_start' => '2024-01-01',
            ],
            [
                'name' => 'Tech Solutions Inc',
                'industry' => 'technology',
                'base_currency' => 'USD',
                'fiscal_year_start' => '2024-01-01',
            ],
            [
                'name' => 'Retail Store',
                'industry' => 'retail',
                'base_currency' => 'USD',
                'fiscal_year_start' => '2024-01-01',
            ],
        ];
    }
}
