<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoicingRequirementsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Get invoicing requirements for a company.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $customerCount = Customer::where('company_id', $company->id)->count();
            $productCount = Product::where('company_id', $company->id)->count();

            $requirements = [
                'has_customers' => $customerCount > 0,
                'has_products' => $productCount > 0,
                'customer_count' => $customerCount,
                'product_count' => $productCount,
                'company_setup_complete' => $company->setup_completed ?? false,
            ];

            return response()->json($requirements);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get invoicing requirements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get setup recommendations.
     */
    public function recommendations(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $customerCount = Customer::where('company_id', $company->id)->count();
            $productCount = Product::where('company_id', $company->id)->count();

            $recommendations = [];

            if ($customerCount === 0) {
                $recommendations[] = [
                    'type' => 'customer',
                    'priority' => 'high',
                    'message' => 'Add customers to create invoices',
                    'action_url' => '/customers/create',
                ];
            }

            if ($productCount === 0) {
                $recommendations[] = [
                    'type' => 'product',
                    'priority' => 'high',
                    'message' => 'Add products or services to invoice',
                    'action_url' => '/products/create',
                ];
            }

            return response()->json($recommendations);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get recommendations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if company can create invoices.
     */
    public function canCreateInvoice(Request $request): JsonResponse
    {
        try {
            $company = $request->user()->currentCompany();

            $customerCount = Customer::where('company_id', $company->id)->count();
            $productCount = Product::where('company_id', $company->id)->count();

            $canCreate = $customerCount > 0 && $productCount > 0;

            return response()->json([
                'can_create' => $canCreate,
                'requirements' => [
                    'has_customers' => $customerCount > 0,
                    'has_products' => $productCount > 0,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to check invoice creation requirements',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
