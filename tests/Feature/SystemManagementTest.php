<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\NavigationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_system_administrator_can_open_and_update_system_management(): void
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($manager)->get(route('system.index'))->assertForbidden();
        $this->actingAs($owner)->get(route('system.index'))->assertOk();
        $this->actingAs($owner)->put(route('system.settings'), [
            'software_name' => 'Firma Kasa',
            'software_tagline' => 'Şantiye Yönetimi',
            'company_name' => 'Örnek İnşaat AŞ',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Firma Kasa', AppSetting::valueOf('software_name'));
    }

    public function test_system_administrator_manages_categories_and_navigation(): void
    {
        $owner = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($owner)->post(route('system.categories.store'), [
            'type' => 'expense', 'name' => 'İş Güvenliği',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('transaction_categories', ['type' => 'expense', 'name' => 'İş Güvenliği']);

        $item = NavigationItem::where('key', 'reports')->firstOrFail();
        $this->actingAs($owner)->put(route('system.navigation'), [
            'items' => [$item->id => ['label' => 'Özel Raporlar', 'sort_order' => 45]],
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('navigation_items', ['id' => $item->id, 'label' => 'Özel Raporlar', 'is_enabled' => false]);
    }

    public function test_company_manager_cannot_see_or_modify_system_administrator(): void
    {
        $manager = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($manager)->get(route('users.index'))->assertDontSee($owner->username);
        $this->actingAs($manager)->get(route('users.edit', $owner))->assertForbidden();
    }
}
