<?php

namespace Modules\Accounting\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoiceController extends Controller
{
    /**
     * List all invoices for the current company.
     */
    public function list(Request $request): JsonResponse
    {
        // Invoice list command - not implemented
        return response()->json([
            'message' => 'Invoice list command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Create a new invoice.
     */
    public function create(Request $request): JsonResponse
    {
        // Invoice create command - not implemented
        return response()->json([
            'message' => 'Invoice create command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Get a specific invoice.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        // Invoice show command - not implemented
        return response()->json([
            'message' => 'Invoice show command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Update an invoice.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        // Invoice update command - not implemented
        return response()->json([
            'message' => 'Invoice update command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Delete an invoice.
     */
    public function delete(Request $request, string $id): JsonResponse
    {
        // Invoice delete command - not implemented
        return response()->json([
            'message' => 'Invoice delete command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Send an invoice to a customer.
     */
    public function send(Request $request, string $id): JsonResponse
    {
        // Invoice send command - not implemented
        return response()->json([
            'message' => 'Invoice send command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }

    /**
     * Record a payment for an invoice.
     */
    public function recordPayment(Request $request, string $id): JsonResponse
    {
        // Invoice record payment command - not implemented
        return response()->json([
            'message' => 'Invoice record payment command - not implemented',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
