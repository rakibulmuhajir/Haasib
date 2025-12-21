<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Services\FuelStationOnboardingService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FuelStationOnboardingController extends Controller
{
    public function __construct(
        private FuelStationOnboardingService $onboardingService
    ) {}

    /**
     * Show onboarding wizard.
     */
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $wizardData = $this->onboardingService->getWizardData($company->id);

        return Inertia::render('FuelStation/Onboarding/Index', [
            'wizard' => $wizardData,
        ]);
    }

    /**
     * Get current onboarding status (for progress checks).
     */
    public function status(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $status = $this->onboardingService->getOnboardingStatus($company->id);

        return Inertia::render('FuelStation/Onboarding/Status', [
            'status' => $status,
        ]);
    }

    /**
     * Create required accounts.
     */
    public function setupAccounts(): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $created = $this->onboardingService->ensureRequiredAccounts($company->id);

        if (empty($created)) {
            return redirect()->back()->with('info', 'All required accounts already exist.');
        }

        return redirect()->back()->with('success', 'Created accounts: ' . implode(', ', $created));
    }

    /**
     * Create default fuel items.
     */
    public function setupFuelItems(): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $created = $this->onboardingService->createDefaultFuelItems($company->id);

        if (empty($created)) {
            return redirect()->back()->with('info', 'Fuel items already exist.');
        }

        return redirect()->back()->with('success', 'Created fuel items: ' . implode(', ', $created));
    }

    /**
     * Complete onboarding and redirect to dashboard.
     */
    public function complete(): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $status = $this->onboardingService->getOnboardingStatus($company->id);

        if (!$status['is_complete']) {
            return redirect()->back()->with('error', 'Please complete all setup steps before proceeding.');
        }

        // Could mark company as having completed fuel station onboarding
        // $company->update(['fuel_station_onboarded_at' => now()]);

        return redirect()->route('fuel.dashboard', ['company' => $company->slug])
            ->with('success', 'Fuel station setup complete! Welcome to your dashboard.');
    }
}
