<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
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
}
