<?php

namespace App\Http\Controllers\Api;

use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\PaletteResolveRequest;
use App\Support\Palette\Commands\ResolveContext;
use App\Support\Palette\Commands\SlotInput;
use App\Support\Palette\PaletteCatalog;
use Illuminate\Http\JsonResponse;

class PaletteResolveController extends Controller
{
    public function __invoke(PaletteResolveRequest $request, PaletteCatalog $catalog): JsonResponse
    {
        $command = $catalog->find($request->user(), CompanyContext::getCompany(), $request->validated('command'));

        if ($command === null) {
            return response()->json(['status' => 'error', 'message' => 'Unknown command'], 404);
        }

        $resolution = $command->resolve(new SlotInput($request->validated('slots', [])), ResolveContext::make());

        return response()->json($resolution->toArray());
    }
}
