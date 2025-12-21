<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Services\FuelDashboardService;
use App\Services\CurrentCompany;
use Inertia\Inertia;
use Inertia\Response;

class FuelDashboardController extends Controller
{
    public function __construct(
        private FuelDashboardService $dashboardService
    ) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $dashboardData = $this->dashboardService->getDashboardData($company->id);

        return Inertia::render('FuelStation/Dashboard/Index', [
            'data' => $dashboardData,
        ]);
    }
}
