<?php

namespace App\Modules\FuelStation;

use App\Constants\Permissions;
use App\Models\Company;
use App\Models\User;
use App\Modules\FuelStation\Http\FuelHome;
use App\Modules\FuelStation\Services\DailyCloseLockService;
use App\Modules\FuelStation\Services\FuelNavigationAccess;
use App\Modules\FuelStation\Services\FuelStationModuleInstaller;
use App\Modules\FuelStation\Services\FuelStationOnboardingService;
use App\Modules\FuelStation\Services\ProfitStatementService;
use App\Modules\FuelStation\Services\StationProductCategories;
use App\Support\Modules\ModuleManifest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FuelStationModule extends ModuleManifest
{
    public function key(): string { return 'fuel_station'; }
    public function name(): string { return 'Fuel Station'; }
    public function industryCodes(): array { return ['fuel_station']; }
    public function requires(): array { return ['inventory']; }
    public function commandPrefixes(): array { return ['fuel.', 'calculator.']; }
    public function capabilities(): array { return ['daily_close']; }
    public function labels(): array { return ['consolidated.item' => 'Fuel', 'consolidated.quantity' => 'Litres']; }

    /** A company that is both fuel and umrah belongs to umrah (it answers first). */
    private function ownsHome(Company $company): bool
    {
        return ! $company->hasModule('umrah');
    }

    public function homeUrl(Company $company): ?string
    {
        return $this->ownsHome($company) ? route('fuel.dashboard', ['company' => $company->slug]) : null;
    }

    /** A new fuel company lands on the company root, which renders the station home. */
    public function afterCreateUrl(Company $company): ?string
    {
        return null;
    }

    public function homeResponse(Request $request, Company $company): mixed
    {
        return $this->ownsHome($company) ? FuelHome::render($request, $company) : null;
    }

    public function onboardingUrl(Company $company): ?string
    {
        return "/{$company->slug}/fuel/onboarding";
    }

    public function onboardingHandoffMessage(): string
    {
        return 'Basic setup complete! Now let\'s configure your fuel station.';
    }

    public function onboardingFlow(): ?string { return 'fuel'; }

    public function prepare(Company $company, ?string $userId): void
    {
        app(FuelStationModuleInstaller::class)->ensureMigrationsApplied();
        app(StationProductCategories::class)->ensureDefaults($company->id, $userId);
    }

    public function prepareOnboarding(Company $company): void
    {
        app(FuelStationModuleInstaller::class)->ensureMigrationsApplied();
    }

    public function afterIdentity(Company $company): mixed
    {
        try {
            $onboarding = app(FuelStationOnboardingService::class);
            $onboarding->ensureRequiredAccounts($company->id);
            $onboarding->createDefaultFuelItems($company->id);
        } catch (\Throwable $e) {
            Log::error('FuelStation module seed failed during onboarding', [
                'company_id' => $company->id ?? null,
                'company_slug' => $company->slug ?? null,
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return redirect("/{$company->slug}/fuel/onboarding")
                ->with('error', 'Company saved, but Fuel Station defaults could not be created. You can continue onboarding and complete Fuel setup later.');
        }

        // For fuel station industry, redirect to the fuel-specific onboarding flow
        return redirect("/{$company->slug}/fuel/onboarding")
            ->with('success', 'Company identity configured. Let\'s set up your fuel station!');
    }

    public function afterBankAccounts(Company $company, array $syncIds): bool
    {
        app(FuelStationOnboardingService::class)->configureAutomaticAccounts($company->fresh(), $syncIds);

        return true;
    }

    public function sharedProps(): array
    {
        return [
            'fuelNavigation' => fn (Company $company, User $user) => app(FuelNavigationAccess::class)->forUser($company, $user),
        ];
    }

    public function settingsSections(Company $company, User $user, callable $section, callable $item, callable $can): array
    {
        $canCompany = $can(Permissions::COMPANY_UPDATE);
        $inventory = $company->isModuleEnabled('inventory');

        return [
            ['before' => 'Accounting', 'section' => $section('Station settings', [
                $item('General', 'Features the station uses', 'fuel/settings#general', $canCompany),
                $item('Monthly fuel profit', 'How month-end stock is valued', 'fuel/settings#monthly-profit', $canCompany),
                $item('Payment channels', 'Cards, banks and wallets the close takes', 'fuel/settings#payment-channels', $canCompany),
                $item('Account mappings', 'The accounts the daily close posts to', 'fuel/settings#accounts', $canCompany),
            ], $canCompany ? 'fuel/settings' : null)],
            ['before' => 'Accounting', 'section' => $section('Station equipment', [
                $item('Tanks', 'Tanks and their fuel', 'warehouses', $inventory && $can(Permissions::WAREHOUSE_VIEW)),
                $item('Pumps & nozzles', 'Pumps, nozzles and meters', 'fuel/pumps', $can(Permissions::PUMP_VIEW)),
                $item('Setup wizard', 'Step-by-step station setup', 'fuel/onboarding', $canCompany),
            ])],
            ['before' => null, 'section' => $section('Help', [
                $item('Help guide', 'How the station works in Haasib', 'fuel/guide'),
            ])],
        ];
    }

    public function vendorTypes(): array
    {
        return ['fuel_refinery', 'fuel_distributor', 'fuel_station', 'lubricant_supplier'];
    }

    public function permissionGroupLabel(): string { return 'Fuel station'; }

    public function permissionPrefixes(): array
    {
        return ['fuel', 'pump', 'tank_reading', 'pump_reading', 'fuel_rate', 'fuel_product', 'investor', 'handover', 'amanat', 'fuel_sale', 'daily_close'];
    }

    public function netProfit(Company $company, string $from, string $to): ?float
    {
        return round((float) app(ProfitStatementService::class)->run($company->id, $from, $to, (string) $company->slug)['net_profit'], 2);
    }

    public function periodLockReason(Company $company, string $from, string $to): ?string
    {
        return app(DailyCloseLockService::class)->isDateInLockedMonth($company->id, $to)
            ? 'That month\'s daily closes are locked.'
            : null;
    }
}
