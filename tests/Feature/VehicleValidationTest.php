<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vehicle_requires_plate_and_machine_requires_code(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $base = ['name' => 'Test', 'tracking_unit' => 'km', 'is_active' => 1];

        $this->actingAs($admin)->post(route('araclar.store'), $base + ['type' => 'vehicle'])->assertSessionHasErrors('plate');
        $this->actingAs($admin)->post(route('araclar.store'), $base + ['type' => 'machine'])->assertSessionHasErrors('code');
    }

    public function test_identifiers_are_normalized_unique_and_irrelevant_field_is_null(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $payload = ['type' => 'vehicle', 'name' => 'Kamyon', 'plate' => ' 34 abc 123 ', 'code' => 'IGNORED', 'tracking_unit' => 'km', 'is_active' => 1];
        $this->actingAs($admin)->post(route('araclar.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vehicles', ['plate' => '34 ABC 123', 'code' => null]);

        $this->actingAs($admin)->post(route('araclar.store'), $payload + ['name' => 'Başka'])->assertSessionHasErrors('plate');
    }

    public function test_personnel_cannot_access_vehicle_management(): void
    {
        $personnel = User::factory()->create(['role' => 'personnel']);
        $this->actingAs($personnel)->get(route('araclar.index'))->assertForbidden();
    }

    public function test_private_vehicle_can_be_created_without_meter_tracking(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('araclar.store'), [
            'type' => 'vehicle', 'name' => 'Yönetici Özel Araç',
            'plate' => '06 OZL 01', 'tracks_meters' => 0, 'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $vehicle = Vehicle::where('plate', '06 OZL 01')->firstOrFail();
        $this->assertFalse($vehicle->tracks_meters);
        $this->actingAs($admin)->get(route('araclar.index'))
            ->assertSee('Sayaç takibi yok');
    }
}
