<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Tanker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_personnel_can_add_but_cannot_update_or_delete_transactions(): void
    {
        $personnel = User::factory()->create(['role' => 'personnel']);
        $transaction = Transaction::create([
            'type' => 'expense', 'category' => 'Malzeme', 'description' => 'Çimento',
            'amount' => 1000, 'occurred_on' => now(), 'created_by' => $personnel->id,
        ]);

        $this->actingAs($personnel)->post(route('transactions.store'), [
            'type' => 'income', 'category' => 'Tahsilat', 'description' => 'Hakediş',
            'amount' => 2500, 'occurred_on' => now()->toDateString(),
        ])->assertRedirect(route('transactions.index'));

        $this->actingAs($personnel)->patch(route('transactions.update', $transaction), [])->assertForbidden();
        $this->actingAs($personnel)->delete(route('transactions.destroy', $transaction), [])->assertForbidden();
    }

    public function test_admin_change_requires_reason_and_creates_audit_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $transaction = Transaction::create([
            'type' => 'expense', 'category' => 'Malzeme', 'description' => 'Çimento',
            'amount' => 1000, 'occurred_on' => now(), 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->patch(route('transactions.update', $transaction), [
            'type' => 'expense', 'category' => 'Malzeme', 'description' => 'Çimento',
            'amount' => 1250, 'occurred_on' => now()->toDateString(), 'reason' => 'Fatura tutarı düzeltildi.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_id' => $transaction->id, 'event' => 'updated',
            'reason' => 'Fatura tutarı düzeltildi.',
        ]);
    }

    public function test_admin_cannot_update_or_delete_financial_record_without_reason(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $transaction = Transaction::create([
            'type' => 'expense', 'category' => 'Malzeme', 'description' => 'Demir',
            'amount' => 1000, 'occurred_on' => now(), 'created_by' => $admin->id,
        ]);
        $payload = [
            'type' => 'expense', 'category' => 'Malzeme', 'description' => 'Demir',
            'amount' => 1200, 'occurred_on' => now()->toDateString(),
        ];

        $this->actingAs($admin)->patch(route('transactions.update', $transaction), $payload)
            ->assertSessionHasErrors('reason');
        $this->actingAs($admin)->delete(route('transactions.destroy', $transaction))
            ->assertSessionHasErrors('reason');
        $this->assertFalse($transaction->fresh()->trashed());
    }

    public function test_audit_history_shows_changes_in_plain_turkish(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AuditLog::query()->create([
            'auditable_type' => Transaction::class,
            'auditable_id' => 42,
            'event' => 'updated',
            'old_values' => ['description' => 'Eski açıklama', 'amount' => '1000.00'],
            'new_values' => ['description' => 'Yeni açıklama', 'amount' => '1250.00'],
            'reason' => 'Fatura düzeltildi.',
            'user_id' => $admin->id,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Kasa hareketi #42')
            ->assertSee('İşlemi yapan:')
            ->assertSee('Açıklama')
            ->assertSee('Eski açıklama')
            ->assertSee('Yeni açıklama')
            ->assertSee('1.000,00 ₺')
            ->assertSee('1.250,00 ₺')
            ->assertDontSee('&quot;description&quot;', false);
    }

    public function test_user_can_be_granted_vehicle_management_without_becoming_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $personnel = User::factory()->create(['role' => 'personnel']);

        $this->actingAs($admin)->put(route('users.update', $personnel), [
            'name' => $personnel->name,
            'username' => $personnel->username,
            'role' => 'personnel',
            'is_active' => 1,
            'permission_form' => 1,
            'permission_keys' => ['dashboard.view', 'vehicles.manage'],
            'reason' => 'Araç işlemlerini yürütmesi için yetki verildi.',
        ])->assertRedirect(route('users.index'));

        $personnel->refresh();
        $this->assertTrue($personnel->hasPermission('vehicles.manage'));
        $this->assertFalse($personnel->isAdmin());

        $this->actingAs($personnel)->get(route('araclar.create'))->assertOk();
        $this->actingAs($personnel)->post(route('araclar.store'), [
            'type' => 'vehicle', 'name' => 'Yetkili Servis', 'plate' => '06 YET 01', 'is_active' => 1,
        ])->assertRedirect(route('araclar.index'));
        $this->assertDatabaseHas('vehicles', ['plate' => '06 YET 01']);
    }

    public function test_user_without_tanker_management_cannot_open_tanker_manager(): void
    {
        $personnel = User::factory()->create(['role' => 'personnel']);

        $this->actingAs($personnel)->get(route('tankers.manage'))->assertForbidden();
        $this->assertTrue(Permission::where('key', 'tankers.manage')->exists());
    }

    public function test_user_form_lists_granular_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('users.create'))
            ->assertOk()
            ->assertSee('Kullanıcı yetkileri')
            ->assertSee('Tanker ekleme / düzenleme / silme')
            ->assertSee('Tanker ekleyebilme')
            ->assertSee('Tanker silebilme')
            ->assertSee('Araç / makine ekleme / düzenleme / silme')
            ->assertSee('Araç / makine düzenleyebilme')
            ->assertSee('Kasa hareket raporu PDF alabilme')
            ->assertSee('permission_keys[]', false);
    }

    public function test_vehicle_permissions_can_be_granted_individually(): void
    {
        $personnel = User::factory()->create(['role' => 'personnel']);
        $personnel->permissions_configured = true;
        $personnel->save();
        $personnel->permissions()->sync(Permission::whereIn('key', ['dashboard.view', 'vehicles.view', 'vehicles.create'])->pluck('id'));
        $vehicle = Vehicle::create(['type' => 'vehicle', 'name' => 'Test Aracı', 'plate' => '06 TEST 01', 'is_active' => true, 'tracks_meters' => true]);

        $this->actingAs($personnel)->get(route('araclar.create'))->assertOk();
        $this->actingAs($personnel)->get(route('araclar.edit', $vehicle))->assertForbidden();
        $this->actingAs($personnel)->delete(route('araclar.destroy', $vehicle))->assertForbidden();

        $this->assertTrue($personnel->hasPermission('vehicles.create'));
        $this->assertFalse($personnel->hasPermission('vehicles.update'));
    }

    public function test_tanker_permissions_can_be_granted_individually(): void
    {
        $personnel = User::factory()->create(['role' => 'personnel']);
        $personnel->permissions_configured = true;
        $personnel->save();
        $personnel->permissions()->sync(Permission::whereIn('key', ['dashboard.view', 'tankers.create'])->pluck('id'));

        $this->actingAs($personnel)->get(route('tankers.manage'))->assertOk();
        $this->actingAs($personnel)->post(route('tankers.store'), ['name' => 'Yetkili Tanker'])
            ->assertRedirect(route('tankers.manage'));
        $tanker = Tanker::where('name', 'Yetkili Tanker')->firstOrFail();
        $this->actingAs($personnel)->patch(route('tankers.update', $tanker), ['name' => 'Yeni Ad', 'is_active' => 1])
            ->assertForbidden();
    }
}
