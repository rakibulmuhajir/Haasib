<?php

namespace App\Http\Middleware;

use App\Models\AuditEntry;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AuditTrail
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only audit write operations (POST, PUT, PATCH, DELETE)
        if (! $this->isWriteOperation($request)) {
            return $response;
        }

        // Don't audit health checks or setup routes
        if ($this->shouldSkipAudit($request)) {
            return $response;
        }

        $this->createAuditEntry($request, $response);

        return $response;
    }

    private function isWriteOperation(Request $request): bool
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }

    private function shouldSkipAudit(Request $request): bool
    {
        $skipPatterns = [
            'setup/*',
            'health',
            'telescope/*',
            'horizon/*',
            '_debugbar/*',
        ];

        foreach ($skipPatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }

    private function createAuditEntry(Request $request, Response $response): void
    {
        try {
            $user = Auth::user();
            $company = $request->attributes->get('company');

            AuditEntry::create([
                'company_id' => $company?->id,
                'user_id' => $user?->id,
                'action' => $this->determineAction($request),
                'resource_type' => $this->getResourceType($request),
                'resource_id' => $this->getResourceId($request),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'request_data' => $this->sanitizeRequestData($request),
                'response_status' => $response->getStatusCode(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            // Fail silently to avoid breaking the application
            // Log the error for debugging
            logger()->error('Audit trail creation failed', [
                'error' => $e->getMessage(),
                'url' => $request->url(),
                'method' => $request->method(),
            ]);
        }
    }

    private function determineAction(Request $request): string
    {
        return match ($request->method()) {
            'POST' => 'create',
            'PUT', 'PATCH' => 'update',
            'DELETE' => 'delete',
            default => 'unknown',
        };
    }

    private function getResourceType(Request $request): ?string
    {
        $route = $request->route();
        if (! $route) {
            return null;
        }

        // Extract resource type from route name or URI
        $uri = $request->decodedPath();
        
        // Handle RESTful routes like /api/v1/companies, /api/v1/users/123
        if (preg_match('/api\/v\d+\/([^\/]+)/', $uri, $matches)) {
            return $matches[1];
        }

        // Handle resource routes like /companies, /invoices/123
        if (preg_match('/^([^\/]+)/', $uri, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function getResourceId(Request $request): ?string
    {
        $route = $request->route();
        if (! $route) {
            return null;
        }

        // Try to get ID from route parameters
        $parameters = $route->parameters();
        foreach (['id', 'company', 'user', 'module', 'invoice'] as $param) {
            if (isset($parameters[$param])) {
                return (string) $parameters[$param];
            }
        }

        return null;
    }

    private function sanitizeRequestData(Request $request): array
    {
        $data = $request->all();

        // Remove sensitive data from audit log
        $sensitiveKeys = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'credit_card',
            'ssn',
        ];

        foreach ($sensitiveKeys as $key) {
            if (isset($data[$key])) {
                $data[$key] = '[REDACTED]';
            }
        }

        // Remove large data that isn't useful for auditing
        unset($data['_token'], $data['_method'], $data['files']);

        return $data;
    }
}