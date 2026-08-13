<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserAndDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_manages_users_last_admin_is_protected_and_password_is_not_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'username' => 'admin']);
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Personel Bir', 'username' => ' personel.1 ', 'role' => 'personnel',
            'is_active' => 1, 'password' => 'Strong-Pass-2026!', 'password_confirmation' => 'Strong-Pass-2026!',
        ])->assertRedirect(route('users.index'));
        $personnel = User::where('username', 'personel.1')->firstOrFail();
        $this->assertTrue(Hash::check('Strong-Pass-2026!', $personnel->password));
        $this->assertStringNotContainsString('password', json_encode(AuditLog::latest('id')->first()->new_values));

        $this->actingAs($admin)->put(route('users.update', $admin), [
            'name' => $admin->name, 'username' => $admin->username, 'role' => 'personnel', 'is_active' => 1,
        ])->assertStatus(422);
        $this->actingAs($personnel)->get(route('users.index'))->assertForbidden();
    }

    public function test_user_can_change_own_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('Old-Strong-2026!')]);
        $this->actingAs($user)->put(route('password.update'), [
            'current_password' => 'Old-Strong-2026!', 'password' => 'New-Strong-2026!',
            'password_confirmation' => 'New-Strong-2026!',
        ])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('New-Strong-2026!', $user->fresh()->password));
    }

    public function test_private_document_upload_download_and_guest_denial(): void
    {
        Storage::fake('documents');
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('transactions.store'), [
            'type' => 'expense', 'category' => 'Belge', 'description' => 'Fatura',
            'amount' => 100, 'occurred_on' => '2026-07-01',
            'document' => UploadedFile::fake()->image('fatura.jpg'),
        ])->assertSessionHasNoErrors();
        $transaction = Transaction::firstOrFail();
        Storage::disk('documents')->assertExists($transaction->document_path);
        $this->get(route('documents.transaction', $transaction))->assertOk();

        auth()->logout();
        $this->get(route('documents.transaction', $transaction))->assertRedirect(route('login'));
    }
}
