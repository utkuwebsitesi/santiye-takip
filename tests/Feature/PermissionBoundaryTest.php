<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MaintenanceEntry;
use App\Models\MobileAccessToken;
use App\Models\Permission;
use App\Models\SystemNotification;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PermissionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_does_not_load_or_render_financial_records_without_resource_permission(): void
    {
        $user = User::factory()->create();
        $this->setPermissions($user, ['dashboard.view']);
        Transaction::create([
            'type' => 'expense', 'category' => 'Gizli', 'description' => 'Sadece muhasebe görür',
            'amount' => 5000, 'occurred_on' => now(), 'created_by' => $user->id,
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()->assertDontSee('Sadece muhasebe görür')->assertViewHas('dashboardMetrics', []);
    }

    public function test_reports_render_only_sections_the_user_can_view(): void
    {
        $user = User::factory()->create();
        $this->setPermissions($user, ['reports.view']);
        Transaction::create([
            'type' => 'expense', 'category' => 'Gizli', 'description' => 'Rapor yetkisi yok',
            'amount' => 1200, 'occurred_on' => now(), 'created_by' => $user->id,
        ]);

        $this->actingAs($user)->get(route('reports.index'))
            ->assertOk()->assertDontSee('Kasa Hareketleri')->assertDontSee('Rapor yetkisi yok');
    }

    public function test_mobile_dashboard_matches_web_permission_boundary(): void
    {
        $user = User::factory()->create();
        $this->setPermissions($user, ['dashboard.view']);
        Transaction::create([
            'type' => 'expense', 'category' => 'Gizli', 'description' => 'Mobilde görünmemeli',
            'amount' => 1200, 'occurred_on' => now(), 'created_by' => $user->id,
        ]);
        $issued = MobileAccessToken::issue($user);

        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->getJson('/api/v1/mobile/dashboard')
            ->assertOk()
            ->assertJsonPath('data.metrics', [])
            ->assertJsonPath('data.recent_fuel', [])
            ->assertJsonPath('data.tankers', [])
            ->assertJsonPath('data.maintenance_alerts', []);
    }

    public function test_restricted_admin_cannot_grant_permissions_it_does_not_own(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->setPermissions($admin, ['dashboard.view', 'users.manage']);

        $payload = [
            'name' => 'Sınırlı Kullanıcı', 'username' => 'sinirli', 'role' => 'personnel', 'is_active' => 1,
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123', 'permission_form' => 1,
            'permission_keys' => ['dashboard.view', 'transactions.view'], 'reason' => 'Görev kapsamı güncellendi.',
        ];

        $this->actingAs($admin)->post(route('users.store'), $payload)->assertSessionHasErrors('permission_keys');
        $this->assertDatabaseMissing('users', ['username' => 'sinirli']);
    }

    public function test_web_and_mobile_user_creation_apply_the_same_explicit_permissions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->setPermissions($admin, ['dashboard.view', 'users.manage', 'transactions.view']);
        $keys = ['dashboard.view', 'transactions.view'];

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Web Personeli', 'username' => 'web.personel', 'role' => 'personnel', 'is_active' => 1,
            'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
            'permission_form' => 1, 'permission_keys' => $keys,
        ])->assertRedirect(route('users.index'));

        $issued = MobileAccessToken::issue($admin);
        $this->withHeader('Authorization', 'Bearer '.$issued['token'])
            ->postJson('/api/v1/mobile/users', [
                'name' => 'Mobil Personeli', 'username' => 'mobil.personel', 'role' => 'personnel', 'is_active' => true,
                'password' => 'StrongPass123', 'password_confirmation' => 'StrongPass123',
                'permission_form' => true, 'permission_keys' => $keys,
            ])->assertCreated();

        foreach (['web.personel', 'mobil.personel'] as $username) {
            $created = User::where('username', $username)->firstOrFail();
            $this->assertTrue($created->permissions_configured);
            $this->assertSame($keys, $created->permissions()->orderBy('key')->pluck('key')->all());
        }
    }

    public function test_permission_change_requires_reason_and_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'personnel']);
        $this->setPermissions($admin, ['dashboard.view', 'users.manage', 'transactions.view']);
        $this->setPermissions($target, ['dashboard.view']);

        $this->actingAs($admin)->put(route('users.update', $target), [
            'name' => $target->name, 'username' => $target->username, 'role' => 'personnel', 'is_active' => 1,
            'permission_form' => 1, 'permission_keys' => ['dashboard.view', 'transactions.view'],
        ])->assertSessionHasErrors('reason');

        $this->actingAs($admin)->put(route('users.update', $target), [
            'name' => $target->name, 'username' => $target->username, 'role' => 'personnel', 'is_active' => 1,
            'permission_form' => 1, 'permission_keys' => ['dashboard.view', 'transactions.view'],
            'reason' => 'Görev kapsamı güncellendi.',
        ])->assertRedirect(route('users.index'));

        $audit = AuditLog::query()->where('auditable_id', $target->id)->where('event', 'permissions_updated')->latest('id')->firstOrFail();
        $this->assertSame(['dashboard.view'], $audit->old_values['permissions']);
        $this->assertSame(['dashboard.view', 'transactions.view'], $audit->new_values['permissions']);
        $this->assertSame('Görev kapsamı güncellendi.', $audit->reason);
    }

    public function test_maintenance_notifications_are_hidden_without_maintenance_permission(): void
    {
        $user = User::factory()->create();
        $this->setPermissions($user, ['notifications.view']);
        $vehicle = Vehicle::create(['type' => 'vehicle', 'name' => 'Kamyon', 'plate' => '06 NOT 01', 'tracking_unit' => 'km', 'is_active' => true, 'tracks_meters' => true]);
        $maintenance = MaintenanceEntry::create([
            'vehicle_id' => $vehicle->id, 'maintenance_date' => now()->toDateString(), 'maintenance_type' => 'Yağ',
            'cost' => 0, 'description' => 'Bakım kaydı', 'created_by' => $user->id,
        ]);
        SystemNotification::create([
            'user_id' => $user->id, 'maintenance_entry_id' => $maintenance->id, 'title' => 'Gizli bakım uyarısı',
            'message' => 'Bu bildirim görünmemeli.', 'link' => route('maintenance.index'),
        ]);

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()->assertDontSee('Gizli bakım uyarısı')->assertSee('Bildirim yok.');
    }

    /** @param array<int, string> $keys */
    private function setPermissions(User $user, array $keys): void
    {
        $user->forceFill(['permissions_configured' => true])->save();
        $user->permissions()->sync(Permission::query()->whereIn('key', $keys)->pluck('id'));
    }
}
