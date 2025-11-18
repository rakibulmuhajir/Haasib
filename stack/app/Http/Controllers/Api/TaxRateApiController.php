<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use App\Services\ServiceContextHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxRateApiController extends Controller
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

        $taxRates = TaxRate::where('company_id', $company->id)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'rate',
                'description',
            ]);

        return response()->json([
            'data' => $taxRates,
            'meta' => [
                'total' => $taxRates->count(),
            ],
        ]);
    }
}
