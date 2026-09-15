<?php

namespace Tests\Unit;

use App\Modules\FuelStation\Services\TankCloseBalance;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TankCloseBalanceTest extends TestCase
{
    public static function dips(): array
    {
        return [
            'first morning' => ['opening', 10000, 0, false, 0, 1000, 10000, 0, 9000],
            'morning with stock' => ['opening', 10000, 10000, true, 0, 1000, 10000, 0, 9000],
            'delivery after dip' => ['opening', 10000, 10000, true, 2000, 1000, 10000, 0, 11000],
            'overnight shortage' => ['opening', 9900, 10000, true, 0, 1000, 10000, -100, 8900],
            'overnight gain' => ['opening', 10100, 10000, true, 0, 1000, 10000, 100, 9100],
            'evening after sales' => ['closing', 9000, 10000, true, 0, 1000, 9000, 0, 9000],
            'evening shortage' => ['closing', 8900, 10000, true, 0, 1000, 9000, -100, 8900],
            'empty closing' => ['closing', 0, 1000, true, 0, 1000, 0, 0, 0],
            'known empty opening' => ['opening', 100, 0, true, 0, 0, 0, 100, 100],
        ];
    }

    #[DataProvider('dips')]
    public function test_timing(string $type, float $dip, float $opening, bool $baseline, float $receipts, float $sales, float $expected, float $variance, float $closing): void
    {
        $this->assertSame(compact('expected', 'variance', 'closing'), app(TankCloseBalance::class)->calculate($type, $dip, $opening, $baseline, $receipts, $sales));
    }

    private function memoryLedger(): void
    {
        // In-memory query fixtures only; no application database is touched.
        config(['database.connections.pgsql' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('pgsql');
        $db = DB::connection('pgsql');
        $db->statement("ATTACH DATABASE ':memory:' AS inv");
        $db->statement("ATTACH DATABASE ':memory:' AS fuel");
        $db->statement('CREATE TABLE inv.stock_movements (company_id TEXT, warehouse_id TEXT, item_id TEXT, movement_date TEXT, movement_type TEXT, quantity REAL, reference_type TEXT)');
        $db->statement('CREATE TABLE fuel.tank_readings (company_id TEXT, tank_id TEXT, reading_date TEXT, reading_type TEXT, dip_measurement_liters REAL, status TEXT, notes TEXT)');
    }

    private function movement(string $date, string $type, float $quantity, ?string $reference = null, string $company = 'company'): void
    {
        DB::connection('pgsql')->table('inv.stock_movements')->insert([
            'company_id' => $company, 'warehouse_id' => 'tank', 'item_id' => 'fuel',
            'movement_date' => $date, 'movement_type' => $type, 'quantity' => $quantity, 'reference_type' => $reference,
        ]);
    }

    public function test_same_day_stock_is_separated_and_next_day_carries_remaining_inventory(): void
    {
        $this->memoryLedger();
        $this->movement('2026-09-15', 'opening', 10000);
        $this->movement('2026-09-15', 'purchase', 2000);
        $this->movement('2026-09-15', 'adjustment_out', -1000, 'fuel.daily_close');
        $this->movement('2026-09-16', 'purchase', 500);
        $this->movement('2026-09-15', 'opening', 99999, null, 'other-company');
        $service = app(TankCloseBalance::class);
        $this->assertSame(10000.0, $service->opening('company', 'tank', 'fuel', '2026-09-15')['liters']);
        $this->assertSame(2000.0, $service->movementsDuringDay('company', 'tank', 'fuel', '2026-09-15'));
        $this->assertSame(11000.0, $service->opening('company', 'tank', 'fuel', '2026-09-16')['liters']);
    }

    public function test_same_day_opening_dip_is_available_without_a_stock_movement(): void
    {
        $this->memoryLedger();
        DB::connection('pgsql')->table('fuel.tank_readings')->insert([
            'company_id' => 'company', 'tank_id' => 'tank', 'reading_date' => '2026-09-15',
            'reading_type' => 'opening', 'dip_measurement_liters' => 10000, 'status' => 'posted',
        ]);
        $this->assertSame(10000.0, app(TankCloseBalance::class)->opening('company', 'tank', 'fuel', '2026-09-15')['liters']);
    }

    public function test_amendment_does_not_use_its_own_dip_as_the_original_baseline(): void
    {
        $this->memoryLedger();
        DB::connection('pgsql')->table('fuel.tank_readings')->insert([
            'company_id' => 'company', 'tank_id' => 'tank', 'reading_date' => '2026-09-15',
            'reading_type' => 'opening', 'dip_measurement_liters' => 10000, 'status' => 'posted', 'notes' => 'Daily close dip',
        ]);
        $this->movement('2026-09-15', 'adjustment_in', 9000, 'fuel.daily_close');
        $this->assertNull(app(TankCloseBalance::class)->opening('company', 'tank', 'fuel', '2026-09-15')['date']);
    }
}
