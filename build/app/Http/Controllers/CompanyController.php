<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyRequest;
use App\Http\Requests\UpdateCompanyRequest;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(): Response
    {
        $companies = Company::query()
            ->orderBy('created_at', 'desc')
            ->get(['id', 'name', 'slug', 'country', 'currency', 'created_at']);

        return Inertia::render('Companies', [
            'companies' => $companies,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Companies/Create');
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        // IMPORTANT: In production, this should use Command Bus
        // For now, we'll do it directly for simplicity
        $company = Company::create($request->validated());

        // TODO: Assign creator as "owner" role for this company
        // This requires CompanyService and RBAC integration

        return response()->json([
            'success' => true,
            'message' => 'Company created successfully',
            'data' => $company,
        ], 201);
    }

    public function show(Company $company): Response
    {
        return Inertia::render('Companies/Show', [
            'company' => $company,
        ]);
    }

    public function edit(Company $company): Response
    {
        return Inertia::render('Companies/Edit', [
            'company' => $company,
        ]);
    }

    public function update(UpdateCompanyRequest $request, Company $company): JsonResponse
    {
        $company->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Company updated successfully',
            'data' => $company,
        ]);
    }

    public function destroy(Company $company): JsonResponse
    {
        // TODO: Add checks - can't delete if has users, invoices, etc.
        $company->delete();

        return response()->json([
            'success' => true,
            'message' => 'Company deleted successfully',
        ]);
    }
}