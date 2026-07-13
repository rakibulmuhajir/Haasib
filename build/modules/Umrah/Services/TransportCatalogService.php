<?php

namespace App\Modules\Umrah\Services;

use App\Modules\Umrah\Models\TransportFare;
use App\Modules\Umrah\Models\TransportPackage;
use App\Modules\Umrah\Models\TransportPackageSector;
use App\Modules\Umrah\Models\TransportSector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransportCatalogService
{
    private const DEFAULT_SECTORS = [
        ['JED-MAK', 'Jeddah Airport to Makkah Hotel', 'Jeddah Airport', 'Makkah Hotel'],
        ['MAK-MED', 'Makkah Hotel to Madinah Hotel', 'Makkah Hotel', 'Madinah Hotel'],
        ['MED-MAK', 'Madinah Hotel to Makkah Hotel', 'Madinah Hotel', 'Makkah Hotel'],
        ['MAK-JED', 'Makkah Hotel to Jeddah Airport', 'Makkah Hotel', 'Jeddah Airport'],
        ['MEDA-MED', 'Madinah Airport to Madinah Hotel', 'Madinah Airport', 'Madinah Hotel'],
        ['MED-MEDA', 'Madinah Hotel to Madinah Airport', 'Madinah Hotel', 'Madinah Airport'],
    ];

    public function ensureDefaultSectors(string $companyId): void
    {
        foreach (self::DEFAULT_SECTORS as $index => [$code, $name, $origin, $destination]) {
            TransportSector::withTrashed()->firstOrCreate(
                ['company_id' => $companyId, 'code' => $code],
                ['name' => $name, 'origin' => $origin, 'destination' => $destination, 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }

    public function createSector(string $companyId, array $data): TransportSector
    {
        return TransportSector::create([
            'company_id' => $companyId,
            'code' => strtoupper(trim($data['code'])),
            'name' => $data['name'],
            'origin' => $data['origin'],
            'destination' => $data['destination'],
            'sort_order' => (int) TransportSector::where('company_id', $companyId)->max('sort_order') + 1,
            'is_active' => true,
        ]);
    }

    public function createPackage(string $companyId, array $data): TransportPackage
    {
        return DB::transaction(function () use ($companyId, $data) {
            $package = TransportPackage::create([
                'company_id' => $companyId,
                'name' => $data['name'],
                'notes' => $data['notes'] ?? null,
                'is_active' => true,
            ]);

            foreach ($data['sector_ids'] as $index => $sectorId) {
                TransportPackageSector::create([
                    'company_id' => $companyId,
                    'transport_package_id' => $package->id,
                    'transport_sector_id' => $sectorId,
                    'sort_order' => $index + 1,
                ]);
            }

            return $package->fresh('sectors');
        });
    }

    public function createFare(string $companyId, array $data): TransportFare
    {
        return TransportFare::create([
            'company_id' => $companyId,
            'transport_service_id' => $data['transport_service_id'],
            'transport_sector_id' => $data['transport_sector_id'] ?? null,
            'transport_package_id' => $data['transport_package_id'] ?? null,
            'name' => $data['name'],
            'charging_basis' => $data['charging_basis'],
            'sale_amount' => round((float) $data['sale_amount'], 2),
            'cost_amount' => round((float) $data['cost_amount'], 2),
            'hajj_terminal_sale_amount' => round((float) ($data['hajj_terminal_sale_amount'] ?? 90), 2),
            'hajj_terminal_cost_amount' => round((float) ($data['hajj_terminal_cost_amount'] ?? 0), 2),
            'is_active' => true,
        ]);
    }

    public function remove(Model $record): void
    {
        if ($record instanceof TransportSector) {
            $inPackage = TransportPackageSector::where('company_id', $record->company_id)
                ->where('transport_sector_id', $record->id)
                ->exists();
            if ($record->fares()->exists() || $inPackage) {
                throw ValidationException::withMessages(['transport' => 'Remove this sector from its packages and fares first.']);
            }
        }

        if ($record instanceof TransportPackage && $record->fares()->exists()) {
            throw ValidationException::withMessages(['transport' => 'Remove this journey package from its fares first.']);
        }

        if ($record->isFillable('is_active')) {
            $record->update(['is_active' => false]);
        }

        $record->delete();
    }
}
