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
        $name = $request->validated('command');

        $command = null;
        foreach ($catalog->commands($request->user(), CompanyContext::getCompany()) as $candidate) {
            if ($candidate->name() === $name) {
                $command = $candidate;
                break;
            }
        }

        if ($command === null) {
            return response()->json(['status' => 'error', 'message' => 'Unknown command'], 404);
        }

        $resolution = $command->resolve(new SlotInput($request->validated('slots', [])), ResolveContext::make());

        return response()->json($resolution->toArray());
    }
}
