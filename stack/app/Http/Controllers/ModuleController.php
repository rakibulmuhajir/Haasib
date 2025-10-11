<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Services\AuthService;
use App\Services\ContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly ContextService $contextService
    ) {}

    /**
     * Get all modules available to the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentCompany = $this->contextService->getCurrentCompany($user);

        if (!$currentCompany) {
            return response()->json([
                'message' => 'No company context',
            ], 400);
        }

        // Get all modules and their status for the current company
        $modules = Module::where('is_active', true)->get();
        $enabledModules = $currentCompany->modules()->wherePivot('is_active', true)->get();

        return response()->json([
            'modules' => $modules->map(function ($module) use ($enabledModules) {
                $isEnabled = $enabledModules->firstWhere('id', $module->id);
                
                return [
                    'id' => $module->id,
                    'name' => $module->name,
                    'key' => $module->key,
                    'description' => $module->description,
                    'category' => $module->category,
                    'version' => $module->version,
                    'is_active' => $module->is_active,
                    'is_enabled' => $isEnabled ? true : false,
                    'enabled_at' => $isEnabled?->pivot->enabled_at,
                ];
            }),
        ]);
    }

    /**
     * Enable a module for the current company.
     */
    public function enable(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $module = Module::findOrFail($id);

        if (!$module->is_active) {
            return response()->json([
                'message' => 'Module is not available',
            ], 400);
        }

        $currentCompany = $this->contextService->getCurrentCompany($user);

        if (!$currentCompany) {
            return response()->json([
                'message' => 'No company context',
            ], 400);
        }

        if (!$this->authService->canAccessCompany($user, $currentCompany, 'manage_modules')) {
            return response()->json([
                'message' => 'Access denied',
            ], 403);
        }

        // Check if already enabled
        if ($currentCompany->modules()->where('modules.id', $module->id)->exists()) {
            return response()->json([
                'message' => 'Module is already enabled',
            ], 400);
        }

        // Enable the module
        $currentCompany->modules()->attach($module->id, [
            'is_active' => true,
            'enabled_at' => now(),
        ]);

        return response()->json([
            'message' => 'Module enabled successfully',
            'module' => [
                'id' => $module->id,
                'name' => $module->name,
                'key' => $module->key,
                'enabled_at' => now(),
            ],
        ]);
    }

    /**
     * Disable a module for the current company.
     */
    public function disable(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        $module = Module::findOrFail($id);

        $currentCompany = $this->contextService->getCurrentCompany($user);

        if (!$currentCompany) {
            return response()->json([
                'message' => 'No company context',
            ], 400);
        }

        if (!$this->authService->canAccessCompany($user, $currentCompany, 'manage_modules')) {
            return response()->json([
                'message' => 'Access denied',
            ], 403);
        }

        // Check if module is enabled
        if (!$currentCompany->modules()->where('modules.id', $module->id)->exists()) {
            return response()->json([
                'message' => 'Module is not enabled',
            ], 400);
        }

        // Disable the module
        $currentCompany->modules()->detach($module->id);

        return response()->json([
            'message' => 'Module disabled successfully',
        ]);
    }
}