<?php

namespace App\Http\Controllers\Api;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Support\Palette\PaletteCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaletteCatalogController extends Controller
{
    public function __invoke(Request $request, PaletteCatalog $catalog): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Authentication required'], 401);
        }

        return response()->json(['commands' => $catalog->for($user, CompanyContext::getCompany())]);
    }
}
