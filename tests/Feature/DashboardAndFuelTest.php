<?php

namespace Tests\Feature;

use App\Models\FuelEntry;
use App\Models\Tanker;
use App\Models\TankerMovement;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardAndFuelTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_calculates_carry_monthly_and_total_balance_without_deleted_rows(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $user = User::factory()->create();
        $user->forceFill(['permissions_configured' => true])->save();
        $user->permissions()->sync(Permission::whereIn('key', array_merge(Permission::defaultKeysForRole('personnel'), ['vehicles.view']))->pluck('id'));
        $make = fn ($type, $amount, $date) => Transaction::create(['type' => $type, 'category' => 'Test', 'description' => 'Test', 'amount' => $amount, 'occurred_on' => $date, 'created_by' => $user->id]);
        $make('income', 1000, '2026-06-01');
        $make('expense', 200, '2026-06-02');
        $make('income', 500, '2026-07-01');
        $make('expense', 100, '2026-07-02');
        $make('expense', 75, '2026-07-15');
        $deleted = $make('income', 9999, '2026-07-03');
        $deleted->delete();
        $vehicle = Vehicle::create([
            'type' => 'vehicle', 'name' => 'Gösterge Kamyonu', 'plate' => '06 GSP 01',
            'tracking_unit' => 'km', 'is_active' => true,
        ]);
        $tanker = Tanker::firstOrFail();
        $tanker->update(['stock_liters' => 350]);
        FuelEntry::create([
            'vehicle_id' => $vehicle->id,
            'tanker_id' => $tanker->id,
            'fuel_date' => '2026-07-15',
            'liters' => 25,
            'unit_price' => 50,
            'total_amount' => 1250,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('poster-stats', false)
            ->assertSee('Tanker Stok Durumu')
            ->assertSee('Araç &amp; Makine Yakıt Takibi', false)
            ->assertSee('Bakım Hatırlatmaları')
            ->assertSee('assets/dashboard-tanker-poster-transparent.png', false)
            ->assertViewHas('carryBalance', fn ($v) => (float) $v === 800.0)
            ->assertViewHas('monthlyNet', fn ($v) => (float) $v === 325.0)
            ->assertViewHas('balance', fn ($v) => (float) $v === 1125.0)
            ->assertViewHas('todayExpense', fn ($v) => (float) $v === 75.0)
            ->assertViewHas('monthlyFuelLiters', fn ($v) => (float) $v === 25.0)
            ->assertViewHas('totalFuelLiters', fn ($v) => (float) $v === 25.0)
            ->assertViewHas('activeVehicleCount', fn ($v) => $v === 1)
            ->assertViewHas('totalTankerStock', fn ($v) => (float) $v === 350.0)
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                return $metrics['fuel']['series'] === [0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 25.0]
                    && $metrics['fleet']['series'] === [1.0, 1.0, 1.0, 1.0, 1.0, 1.0, 1.0];
            });
    }

    public function test_inactive_vehicle_is_rejected_total_is_server_calculated_and_meter_is_chronological(): void
    {
        $personnel = User::factory()->create();
        $active = Vehicle::create(['type' => 'vehicle', 'name' => 'Kamyon', 'plate' => '34 TEST 1', 'tracking_unit' => 'km', 'is_active' => true]);
        $inactive = Vehicle::create(['type' => 'vehicle', 'name' => 'Pasif', 'plate' => '34 TEST 2', 'tracking_unit' => 'km', 'is_active' => false]);
        $tanker = Tanker::firstOrFail();
        $tanker->update(['stock_liters' => 1000, 'average_unit_cost' => 42.75]);
        $base = ['tanker_id' => $tanker->id, 'fuel_time' => '10:00', 'liters' => '10.125', 'unit_price' => '42.750', 'meter_value' => 100, 'total_amount' => 1];

        $this->actingAs($personnel)->post(route('fuel.store'), $base + ['vehicle_id' => $inactive->id, 'fuel_date' => '2026-07-01'])->assertSessionHasErrors('vehicle_id');
        $this->actingAs($personnel)->post(route('fuel.store'), $base + ['vehicle_id' => $active->id, 'fuel_date' => '2026-07-01'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('fuel_entries', ['vehicle_id' => $active->id, 'total_amount' => '432.84']);

        $this->actingAs($personnel)->post(route('fuel.store'), array_merge($base, ['vehicle_id' => $active->id, 'fuel_date' => '2026-07-03', 'meter_value' => 300]))->assertSessionHasNoErrors();
        $this->actingAs($personnel)->post(route('fuel.store'), array_merge($base, ['vehicle_id' => $active->id, 'fuel_date' => '2026-07-02', 'meter_value' => 400]))->assertSessionHasErrors('meter_value');
        $this->actingAs($personnel)->post(route('fuel.store'), array_merge($base, ['vehicle_id' => $active->id, 'fuel_date' => '2026-06-30', 'meter_value' => 150]))->assertSessionHasErrors('meter_value');
    }

    public function test_fuel_expense_creates_vehicle_record_without_reducing_cash_balance(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $personnel = User::factory()->create();
        $vehicle = Vehicle::create([
            'type' => 'vehicle', 'name' => 'Kamyon', 'plate' => '34 YAKIT 1',
            'tracking_unit' => 'km', 'is_active' => true,
        ]);
        $tanker = Tanker::firstOrFail();
        $tanker->update(['stock_liters' => 1000, 'average_unit_cost' => 40.25]);

        $this->actingAs($personnel)->post(route('transactions.store'), [
            'type' => 'expense',
            'is_fuel_expense' => 1,
            'description' => 'Sefer yakıtı',
            'occurred_on' => '2026-07-10',
            'vehicle_id' => $vehicle->id,
            'tanker_id' => $tanker->id,
            'fuel_time' => '09:30',
            'liters' => '25.500',
            'unit_price' => '40.250',
            'meter_value' => 12000,
            'station' => 'Test İstasyonu',
        ])->assertSessionHasNoErrors()
            ->assertRedirect(route('fuel.index'));

        $transaction = Transaction::firstOrFail();
        $fuel = FuelEntry::firstOrFail();
        $this->assertSame('expense', $transaction->type);
        $this->assertSame('Yakıt', $transaction->category);
        $this->assertSame('1026.38', $transaction->amount);
        $this->assertFalse($transaction->affects_cash);
        $this->assertSame($transaction->id, $fuel->transaction_id);
        $this->assertSame($vehicle->id, $fuel->vehicle_id);
        $this->assertSame('1026.38', $fuel->total_amount);

        $this->actingAs($personnel)->get(route('dashboard'))
            ->assertViewHas('monthlyExpense', fn ($value) => (float) $value === 0.0)
            ->assertViewHas('monthlyNet', fn ($value) => (float) $value === 0.0)
            ->assertViewHas('balance', fn ($value) => (float) $value === 0.0)
            ->assertViewHas('fuel', fn ($value) => (float) $value === 1026.38);

        $this->actingAs($personnel)->get(route('transactions.index'))
            ->assertDontSee('Sefer yakıtı');
        $this->actingAs($personnel)->get(route('fuel.index'))
            ->assertSee('Test İstasyonu')
            ->assertSee('1.026,38 ₺');
    }

    public function test_fuel_vehicle_filter_is_available_as_auto_submit_control(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::create([
            'type' => 'vehicle', 'name' => 'Filtre Kamyonu', 'plate' => '34 FLT 01',
            'tracking_unit' => 'km', 'is_active' => true,
        ]);

        $this->actingAs($user)->get(route('fuel.index', ['vehicle_id' => $vehicle->id]))
            ->assertOk()
            ->assertSee('name="vehicle_id" data-auto-submit', false)
            ->assertSee('34 FLT 01');
    }

    public function test_kilometer_and_operating_hours_are_tracked_and_validated_independently(): void
    {
        $personnel = User::factory()->create();
        $machine = Vehicle::create([
            'type' => 'machine', 'name' => 'Ekskavatör', 'code' => 'EX-01',
            'tracking_unit' => 'km', 'is_active' => true,
        ]);
        $tanker = Tanker::firstOrFail();
        $tanker->update(['stock_liters' => 1000, 'average_unit_cost' => 50]);
        $base = [
            'vehicle_id' => $machine->id, 'tanker_id' => $tanker->id, 'fuel_time' => '10:00',
            'liters' => 20, 'unit_price' => 50,
        ];

        $this->actingAs($personnel)->post(route('fuel.store'), $base + [
            'fuel_date' => '2026-07-01', 'meter_value' => 1000, 'operating_hours' => 100,
        ])->assertSessionHasNoErrors();
        $this->actingAs($personnel)->post(route('fuel.store'), $base + [
            'fuel_date' => '2026-07-03', 'meter_value' => 1200, 'operating_hours' => 300,
        ])->assertSessionHasNoErrors();
        $this->actingAs($personnel)->get(route('fuel.index', ['vehicle_id' => $machine->id]))
            ->assertSee('10,00 L')
            ->assertSee('0,10 L')
            ->assertSee('100 kilometrede')
            ->assertSee('Çalışma saatinde');
        $this->actingAs($personnel)->post(route('fuel.store'), $base + [
            'fuel_date' => '2026-07-02', 'meter_value' => 1100, 'operating_hours' => 400,
        ])->assertSessionHasErrors('operating_hours');

        $this->assertDatabaseHas('fuel_entries', [
            'vehicle_id' => $machine->id, 'meter_value' => '1200', 'operating_hours' => '300',
        ]);
    }

    public function test_tanker_purchase_reduces_cash_and_vehicle_issue_reduces_only_stock(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $user = User::factory()->create();
        $user->forceFill(['permissions_configured' => true])->save();
        $user->permissions()->sync(Permission::whereIn('key', array_merge(Permission::defaultKeysForRole('personnel'), ['vehicles.view']))->pluck('id'));
        $tanker = Tanker::firstOrFail();
        $vehicle = Vehicle::create([
            'type' => 'vehicle', 'name' => 'Servis Aracı', 'plate' => '06 STK 01',
            'tracking_unit' => 'km', 'is_active' => true,
        ]);

        $this->actingAs($user)->post(route('tankers.purchase.store'), [
            'tanker_id' => $tanker->id, 'movement_date' => '2026-07-15',
            'liters' => 1000, 'unit_cost' => 45, 'supplier' => 'Rafineri',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transactions', [
            'category' => 'Yakıt Alımı', 'amount' => '45000', 'affects_cash' => true,
        ]);
        $this->actingAs($user)->post(route('tankers.purchase.store'), [
            'tanker_id' => $tanker->id, 'movement_date' => '2026-07-15',
            'liters' => 1000, 'unit_cost' => 55, 'supplier' => 'Rafineri',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('fuel.store'), [
            'tanker_id' => $tanker->id, 'vehicle_id' => $vehicle->id,
            'fuel_date' => '2026-07-15', 'liters' => 100, 'meter_value' => 1000,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1900.0, (float) $tanker->fresh()->stock_liters);
        $this->assertSame(55.0, (float) $tanker->fresh()->average_unit_cost);
        $this->assertDatabaseHas('fuel_entries', [
            'vehicle_id' => $vehicle->id, 'liters' => '100', 'unit_price' => '55', 'total_amount' => '5500',
        ]);
        $this->assertSame(3, TankerMovement::count());
        $this->actingAs($user)->get(route('dashboard'))
            ->assertViewHas('expense', fn ($value) => (float) $value === 100000.0)
            ->assertViewHas('fuel', fn ($value) => (float) $value === 5500.0)
            ->assertViewHas('balance', fn ($value) => (float) $value === -100000.0);

        $emptyTanker = Tanker::create(['name' => 'Boş Tanker']);
        $this->actingAs($user)->post(route('fuel.store'), [
            'tanker_id' => $emptyTanker->id, 'vehicle_id' => $vehicle->id,
            'fuel_date' => '2026-07-16', 'liters' => 1,
        ])->assertSessionHasErrors('liters');
    }

    public function test_admin_can_add_a_tanker_that_automatically_appears_in_dashboard_and_reports(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->assertSame(1, Tanker::count());

        $this->actingAs($admin)->post(route('tankers.store'), ['name' => 'Kuzey Şantiye Tankeri'])
            ->assertRedirect(route('tankers.manage'));

        $this->assertDatabaseHas('tankers', [
            'name' => 'Kuzey Şantiye Tankeri',
            'stock_liters' => '0.000',
            'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Kuzey Şantiye Tankeri');
        $this->actingAs($admin)->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Tanker Stok Özeti')
            ->assertSee('Kuzey Şantiye Tankeri');
    }

    public function test_dashboard_charts_and_arrows_use_real_seven_day_metrics(): void
    {
        Carbon::setTestNow('2026-07-15 12:00:00');
        $user = User::factory()->create();
        $user->forceFill(['permissions_configured' => true])->save();
        $user->permissions()->sync(Permission::whereIn('key', array_merge(Permission::defaultKeysForRole('personnel'), ['vehicles.view']))->pluck('id'));
        Transaction::create([
            'type' => 'income', 'category' => 'Tahsilat', 'description' => 'Dün tahsilat',
            'amount' => 1000, 'occurred_on' => '2026-07-14', 'created_by' => $user->id,
        ]);
        Transaction::create([
            'type' => 'expense', 'category' => 'Malzeme', 'description' => 'Bugün harcama',
            'amount' => 250, 'occurred_on' => '2026-07-15', 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-sparkline="cash"', false)
            ->assertDontSee('@else', false)
            ->assertSee('Düne göre bakiye azaldı: 250,00 ₺')
            ->assertViewHas('dashboardMetrics', function (array $metrics): bool {
                return count($metrics['cash']['series']) === 7
                    && $metrics['cash']['trend']['direction'] === 'down'
                    && $metrics['expense']['trend']['tone'] === 'negative'
                    && $metrics['fuel']['series'] === [0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0]
                    && $metrics['fleet']['series'] === [0.0, 0.0, 0.0, 0.0, 0.0, 0.0, 0.0];
            });
    }

    public function test_dashboard_compact_recent_list_keeps_older_recent_dates_accessible(): void
    {
        Carbon::setTestNow('2026-08-22 12:00:00');
        $user = User::factory()->create();
        $dates = array_merge(
            array_fill(0, 4, '2026-08-22'),
            array_fill(0, 10, '2026-08-21'),
            array_fill(0, 7, '2026-08-20'),
            array_fill(0, 4, '2026-08-19'),
        );

        foreach ($dates as $index => $date) {
            Transaction::create([
                'type' => 'expense', 'category' => 'Test', 'description' => 'Kompakt hareket '.$index,
                'amount' => 100 + $index, 'occurred_on' => $date, 'created_by' => $user->id,
            ]);
        }

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('19.08.2026')
            ->assertSee('data-inline-pagination', false)
            ->assertSee('data-page-size="10"', false)
            ->assertSee('Sonraki', false)
            ->assertViewHas('recentTransactions', fn ($items): bool => $items->count() === 25);
    }

    public function test_admin_can_delete_only_a_tanker_without_any_history(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $empty = Tanker::create(['name' => 'Silinebilir Tanker']);

        $this->actingAs($admin)->delete(route('tankers.destroy', $empty))
            ->assertRedirect(route('tankers.manage'));
        $this->assertDatabaseMissing('tankers', ['id' => $empty->id]);

        $used = Tanker::create(['name' => 'Geçmişi Olan Tanker']);
        TankerMovement::create([
            'tanker_id' => $used->id, 'type' => 'purchase', 'movement_date' => '2026-07-30',
            'liters' => 1, 'unit_cost' => 50, 'total_amount' => 50, 'balance_after' => 1,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->delete(route('tankers.destroy', $used))
            ->assertSessionHasErrors('tanker');
        $this->assertDatabaseHas('tankers', ['id' => $used->id]);

        $this->actingAs($admin)->get(route('tankers.manage'))
            ->assertOk()
            ->assertSee('Engel: 1 hareket · 0 yakıt kaydı');

        $stocked = Tanker::create(['name' => 'Stoklu Tanker', 'stock_liters' => 1]);
        $this->actingAs($admin)->delete(route('tankers.destroy', $stocked))
            ->assertSessionHasErrors('tanker');
        $this->assertDatabaseHas('tankers', ['id' => $stocked->id]);

        $archived = Tanker::create(['name' => 'Arşivli Tanker']);
        $vehicle = Vehicle::create([
            'type' => 'vehicle', 'name' => 'Arşiv Test Aracı', 'plate' => '06 ARS 01',
            'tracking_unit' => 'km', 'is_active' => true,
        ]);
        $archivedMovement = TankerMovement::create([
            'tanker_id' => $archived->id, 'type' => 'purchase', 'movement_date' => '2026-07-30',
            'liters' => 1, 'unit_cost' => 50, 'total_amount' => 50, 'balance_after' => 1,
            'created_by' => $admin->id,
        ]);
        $archivedFuel = FuelEntry::create([
            'vehicle_id' => $vehicle->id, 'tanker_id' => $archived->id, 'fuel_date' => '2026-07-30',
            'liters' => 1, 'unit_price' => 50, 'total_amount' => 50, 'created_by' => $admin->id,
        ]);
        $archivedMovement->delete();
        $archivedFuel->delete();

        $this->actingAs($admin)->get(route('tankers.manage'))
            ->assertOk()
            ->assertSee('Arşivde: 1 hareket · 1 yakıt kaydı')
            ->assertSee(route('tankers.purge', $archived), false);
        $this->actingAs($admin)->delete(route('tankers.destroy', $archived))
            ->assertSessionHasErrors('tanker');
        $this->actingAs($admin)->delete(route('tankers.purge', $archived))
            ->assertRedirect(route('tankers.manage'));
        $this->assertDatabaseMissing('tankers', ['id' => $archived->id]);
        $this->assertDatabaseMissing('tanker_movements', ['id' => $archivedMovement->id]);
        $this->assertDatabaseMissing('fuel_entries', ['id' => $archivedFuel->id]);
    }

    public function test_personnel_cannot_manage_tankers(): void
    {
        $personnel = User::factory()->create();

        $this->actingAs($personnel)->get(route('tankers.manage'))->assertForbidden();
        $this->actingAs($personnel)->post(route('tankers.store'), ['name' => 'Yetkisiz Tanker'])->assertForbidden();
    }
}
