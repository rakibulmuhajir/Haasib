<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ServiceContextHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $context = ServiceContextHelper::fromRequest($request);
        $company = $context->getCompany();

        if (! $company) {
            return response()->json([
                'message' => 'Company context not found',
                'data' => [],
            ], 422);
        }

        $query = Product::where('company_id', $company->id)
            ->orderBy('name');

        if ($search = $request->get('search')) {
            $query->search($search);
        }

        $products = $query->limit(50)->get([
            'id',
            'name',
            'description',
            'unit_price',
            'tax_rate',
            'product_code',
        ]);

        return response()->json([
            'data' => $products,
            'meta' => [
                'total' => $products->count(),
            ],
        ]);
    }
}
