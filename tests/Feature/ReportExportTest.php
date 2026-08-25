<?php

namespace Tests\Feature;

use App\Models\FuelEntry;
use App\Models\Tanker;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_exports_are_separate_from_report_view_permission(): void
    {
        $personnel = User::factory()->create(['role' => 'personnel']);

        $this->actingAs($personnel)->get(route('reports.cash.pdf'))->assertForbidden();
        $this->actingAs($personnel)->get(route('reports.fuel.excel'))->assertForbidden();
    }

    public function test_cash_reports_can_be_downloaded_as_excel_and_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Transaction::create([
            'type' => 'expense', 'category' => 'Yemek', 'description' => 'Saha yemek gideri',
            'amount' => 1250, 'occurred_on' => '2026-08-24', 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('reports.cash.excel'))
            ->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->assertDownload('kasa-hareket-raporu.xlsx');
        $this->actingAs($admin)->get(route('reports.cash.pdf'))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload('kasa-hareket-raporu.pdf')
            ->assertSee('%PDF-1.4', false)
            ->assertSee("BT\n/F2", false)
            ->assertSee('Saha yemek gideri', false)
            ->assertSee('Toplam Gider', false)
            ->assertSee('Sayfa 1 / 1', false);
    }

    public function test_fuel_reports_can_be_downloaded_as_excel_and_pdf(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $vehicle = Vehicle::create(['type' => 'vehicle', 'name' => 'Kamyon', 'plate' => '06 RPR 01', 'is_active' => true, 'tracks_meters' => true]);
        $tanker = Tanker::create(['name' => 'Tanker Rapor', 'stock_liters' => 500, 'average_unit_cost' => 42.5, 'is_active' => true]);
        FuelEntry::create([
            'vehicle_id' => $vehicle->id, 'tanker_id' => $tanker->id, 'fuel_date' => '2026-08-24',
            'liters' => 100, 'unit_price' => 42.5, 'total_amount' => 4250, 'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)->get(route('reports.fuel.excel'))
            ->assertOk()->assertDownload('yakit-raporu.xlsx');
        $this->actingAs($admin)->get(route('reports.fuel.pdf'))
            ->assertOk()->assertDownload('yakit-raporu.pdf')
            ->assertSee('%PDF-1.4', false)
            ->assertSee("BT\n/F2", false)
            ->assertSee('06 RPR 01', false)
            ->assertSee('Kamyon', false)
            ->assertSee('Toplam Litre', false)
            ->assertSee('Sayfa 1 / 1', false);
    }
}
