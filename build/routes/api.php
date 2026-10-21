<?php

use App\Http\Controllers\CommandController;
use App\Http\Controllers\Api\PaletteCatalogController;
use App\Http\Controllers\Api\PaletteDefaultsController;
use App\Http\Controllers\Api\PaletteFindController;
use App\Http\Controllers\Api\PalettePreviewController;
use App\Http\Controllers\Api\PaletteResolveController;
use Illuminate\Support\Facades\Route;

Route::post('/commands', CommandController::class)
    ->middleware(['web', 'auth', 'identify.company', 'throttle:commands']);

Route::get('/palette/catalog', PaletteCatalogController::class)
    ->middleware(['web', 'auth', 'identify.company', 'throttle:60,1']);

Route::get('/palette/defaults', PaletteDefaultsController::class)
    ->middleware(['web', 'auth', 'identify.company', 'throttle:60,1']);

Route::get('/palette/open-bills', [PaletteDefaultsController::class, 'openBills'])
    ->middleware(['web', 'auth', 'identify.company', 'throttle:60,1']);

Route::post('/palette/preview', PalettePreviewController::class)
    ->middleware(['web', 'auth', 'identify.company', 'throttle:60,1']);

Route::get('/palette/find', PaletteFindController::class)
    ->middleware(['web', 'auth', 'identify.company', 'throttle:120,1']);

Route::post('/palette/resolve', PaletteResolveController::class)
    ->middleware(['web', 'auth', 'identify.company', 'throttle:120,1']);

Route::post('/palette/execute', function (Illuminate\Http\Request $request) {
    $controller = new App\Http\Controllers\CommandController();

    // Extract entity and verb from request body
    $entity = $request->input('entity');
    $verb = $request->input('verb');
    $params = $request->input('params', []);

    if (!$entity || !$verb) {
        return response()->json([
            'ok' => false,
            'code' => 'BAD_REQUEST',
            'message' => 'Entity and verb are required',
        ], 400);
    }

    // Create action string in format expected by CommandController
    $action = "{$entity}.{$verb}";

    // Set the action header and forward to CommandController
    $request->headers->set('X-Action', $action);

    // Merge params into request input for CommandController
    $request->merge(['params' => $params]);

    return $controller($request);
})->middleware(['web', 'auth', 'identify.company', 'throttle:60,1']);
