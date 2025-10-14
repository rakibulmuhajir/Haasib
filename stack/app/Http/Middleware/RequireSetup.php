<?php

namespace App\Http\Middleware;

use App\Services\SetupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSetup
{
    public function __construct(private readonly SetupService $setupService) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->setupService->isInitialized()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Platform must be initialized first',
                    'setup_required' => true,
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            return redirect()->route('setup.page')
                ->with('error', 'Platform must be initialized first');
        }

        return $next($request);
    }
}