<?php

namespace Tests\Feature;

use App\Models\FuelEntry;
use App\Models\MaintenanceEntry;
use App\Models\SystemNotification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_personnel_records_vehicle_maintenance_and_optionally_links_cash_expense(): void
    {
        $personnel = User::factory()->create();
        $vehicle = $this->vehicle();

        $this->actingAs($personnel)->post(route('maintenance.store'), [
            'vehicle_id' => $vehicle->id,
            'maintenance_date' => '2026-07-30',
            'maintenance_type' => 'Periyodik Bakım',
            'service_provider' => 'Örnek Servis',
            'cost' => '2500.50',
            'meter_value' => 45000,
            'operating_hours' => 4200,
            'next_maintenance_date' => '2027-01-30',
            'next_meter_value' => 55000,
            'next_operating_hours' => 5000,
            'description' => 'Yağ ve filtreler değiştirildi.',
            'record_as_expense' => 1,
        ])->assertRedirect(route('maintenance.index'))->assertSessionHasNoErrors();

        $maintenance = MaintenanceEntry::firstOrFail();
        $transaction = Transaction::firstOrFail();
        $this->assertSame($vehicle->id, $maintenance->vehicle_id);
        $this->assertSame($transaction->id, $maintenance->transaction_id);
        $this->assertSame('4200.0', $maintenance->operating_hours);
        $this->assertSame('5000.0', $maintenance->next_operating_hours);
        $this->assertSame('Bakım / Onarım', $transaction->category);
        $this->assertSame('2500.50', $transaction->amount);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_personnel_cannot_edit_but_admin_can_update_with_reason(): void
    {
        $personnel = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $maintenance = MaintenanceEntry::create([
            'vehicle_id' => $this->vehicle()->id,
            'maintenance_date' => '2026-07-30',
            'maintenance_type' => 'Lastik',
            'cost' => 1000,
            'description' => 'İki lastik değişti.',
            'created_by' => $personnel->id,
        ]);
        $payload = [
            'vehicle_id' => $maintenance->vehicle_id,
            'maintenance_date' => '2026-07-30',
            'maintenance_type' => 'Lastik',
            'cost' => 1250,
            'description' => 'Dört lastik değişti.',
            'reason' => 'Fatura tutarı düzeltildi.',
        ];

        $this->actingAs($personnel)->patch(route('maintenance.update', $maintenance), $payload)->assertForbidden();
        $this->actingAs($admin)->patch(route('maintenance.update', $maintenance), $payload)
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('maintenance_entries', ['id' => $maintenance->id, 'cost' => '1250']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => MaintenanceEntry::class, 'event' => 'updated']);
    }

    public function test_inactive_vehicle_is_rejected_for_new_maintenance(): void
    {
        $personnel = User::factory()->create();
        $vehicle = $this->vehicle(false);

        $this->actingAs($personnel)->post(route('maintenance.store'), [
            'vehicle_id' => $vehicle->id,
            'maintenance_date' => '2026-07-30',
            'maintenance_type' => 'Motor',
            'cost' => 500,
            'description' => 'Kontrol.',
        ])->assertSessionHasErrors('vehicle_id');
    }

    public function test_maintenance_expense_from_cash_entry_requires_a_vehicle_and_is_added_to_vehicle_reports(): void
    {
        $personnel = User::factory()->create();
        $vehicle = $this->vehicle();
        $payload = [
            'type' => 'expense',
            'category' => 'Bakım / Onarım',
            'description' => 'Fren balataları değiştirildi.',
            'amount' => '1850.50',
            'occurred_on' => '2026-07-30',
        ];

        $this->actingAs($personnel)->post(route('transactions.store'), $payload)
            ->assertSessionHasErrors('maintenance_vehicle_id');

        $this->actingAs($personnel)->post(route('transactions.store'), $payload + [
            'maintenance_vehicle_id' => $vehicle->id,
            'maintenance_service_provider' => 'Ankara Fren Servisi',
        ])->assertRedirect(route('maintenance.index', ['vehicle_id' => $vehicle->id]))
            ->assertSessionHasNoErrors();

        $transaction = Transaction::where('description', 'Fren balataları değiştirildi.')->sole();
        $this->assertDatabaseHas('maintenance_entries', [
            'transaction_id' => $transaction->id,
            'vehicle_id' => $vehicle->id,
            'maintenance_type' => 'Bakım / Onarım',
            'service_provider' => 'Ankara Fren Servisi',
            'cost' => '1850.50',
        ]);

        $this->actingAs($personnel)->get(route('maintenance.index', ['vehicle_id' => $vehicle->id]))
            ->assertOk()
            ->assertSee('Fren balataları değiştirildi.');
        $this->actingAs($personnel)->get(route('reports.index', ['vehicle_id' => $vehicle->id]))
            ->assertOk()
            ->assertSee('Araç / Makine Bakım Geçmişi')
            ->assertSee('Fren balataları değiştirildi.')
            ->assertSee('Ankara Fren Servisi');
    }

    public function test_due_maintenance_is_shown_as_global_bottom_corner_alert(): void
    {
        Carbon::setTestNow('2026-08-30 10:00:00');
        $user = User::factory()->create();
        $vehicle = $this->vehicle();
        MaintenanceEntry::create([
            'vehicle_id' => $vehicle->id,
            'maintenance_date' => '2026-07-30',
            'maintenance_type' => 'Periyodik Bakım',
            'cost' => 0,
            'description' => 'Planlı bakım',
            'next_maintenance_date' => '2026-08-30',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bakım zamanı geldi')
            ->assertSee('Bakım tarihi: 30.08.2026');

        $notification = SystemNotification::firstOrFail();
        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-auto-dismiss="8000"', false)
            ->assertSee($notification->title);
        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Bildirim Geçmişi')
            ->assertSee('Bakım tarihi: 30.08.2026');
        $this->actingAs($user)->get(route('notifications.open', $notification))
            ->assertRedirect(route('maintenance.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_kilometer_and_hour_thresholds_trigger_reminder_and_new_plan_supersedes_old_one(): void
    {
        Carbon::setTestNow('2026-08-01 10:00:00');
        $user = User::factory()->create();
        $vehicle = $this->vehicle();
        FuelEntry::create([
            'vehicle_id' => $vehicle->id, 'fuel_date' => '2026-08-01', 'fuel_time' => '09:00',
            'liters' => 10, 'unit_price' => 50, 'total_amount' => 500,
            'meter_value' => 6000, 'operating_hours' => 356, 'created_by' => $user->id,
        ]);
        MaintenanceEntry::create([
            'vehicle_id' => $vehicle->id, 'maintenance_date' => '2026-07-01',
            'maintenance_type' => 'Yağ / Filtre', 'cost' => 0, 'description' => 'Eski plan',
            'next_maintenance_date' => '2026-07-15', 'created_by' => $user->id,
        ]);
        MaintenanceEntry::create([
            'vehicle_id' => $vehicle->id, 'maintenance_date' => '2026-07-30',
            'maintenance_type' => 'Yağ / Filtre', 'cost' => 0, 'description' => 'Yeni plan',
            'next_meter_value' => 6000, 'next_operating_hours' => 356, 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertSee('KM sınırı: 6.000 km')
            ->assertSee('Saat sınırı: 356,0 saat')
            ->assertDontSee('Bakım tarihi: 15.07.2026');
    }

    public function test_admin_can_open_maintenance_edit_page_when_linked_vehicle_is_inactive(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = $this->vehicle(false);
        $maintenance = MaintenanceEntry::create([
            'vehicle_id' => $vehicle->id,
            'maintenance_date' => '2026-07-30',
            'maintenance_type' => 'Motor',
            'cost' => 500,
            'description' => 'Kontrol',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('maintenance.edit', $maintenance))
            ->assertOk()
            ->assertSee($vehicle->plate);
    }

    private function vehicle(bool $active = true): Vehicle
    {
        return Vehicle::create([
            'type' => 'vehicle',
            'name' => 'Servis Kamyonu',
            'plate' => '34 BKM 01',
            'tracking_unit' => 'km',
            'is_active' => $active,
        ]);
    }
}
