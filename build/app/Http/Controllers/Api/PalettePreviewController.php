<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PalettePreviewRequest;
use App\Support\Palette\PalettePreview;
use Illuminate\Http\JsonResponse;

/** POST /api/palette/preview: what a command would post, without posting it. */
class PalettePreviewController extends Controller
{
    public function __invoke(PalettePreviewRequest $request): JsonResponse
    {
        $result = PalettePreview::run(
            $request->validated('key'),
            $request->validated('params') ?? [],
            $request->user()
        );

        $status = $result['ok'] ? 200 : match ($result['code'] ?? '') {
            'FORBIDDEN' => 403,
            'UNAUTHORIZED' => 401,
            'NOT_FOUND' => 404,
            'SERVER_ERROR' => 500,
            default => 422,
        };

        return response()->json($result, $status)->header('Cache-Control', 'no-store');
    }
}
