<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_filters_transactions_by_date_category_and_creator(): void
    {
        $viewer = User::factory()->create();
        $wantedCreator = User::factory()->create();
        $otherCreator = User::factory()->create();
        $wanted = Transaction::create([
            'type' => 'expense', 'category' => 'Malzeme', 'description' => 'Dahil',
            'amount' => 100, 'occurred_on' => '2026-07-10', 'created_by' => $wantedCreator->id,
        ]);
        Transaction::create([
            'type' => 'expense', 'category' => 'Yemek', 'description' => 'Hariç',
            'amount' => 200, 'occurred_on' => '2026-07-20', 'created_by' => $otherCreator->id,
        ]);

        $this->actingAs($viewer)->get(route('reports.index', [
            'from' => '2026-07-01', 'to' => '2026-07-15',
            'category' => 'Malzeme', 'created_by' => $wantedCreator->id,
        ]))->assertOk()->assertViewHas('transactions', function ($paginator) use ($wanted): bool {
            return $paginator->count() === 1 && $paginator->first()->is($wanted);
        });
    }

    public function test_report_lists_show_ten_records_per_page(): void
    {
        $viewer = User::factory()->create();
        foreach (range(1, 11) as $index) {
            Transaction::create([
                'type' => 'expense', 'category' => 'Test', 'description' => 'Kayıt '.$index,
                'amount' => $index * 10, 'occurred_on' => '2026-08-01', 'created_by' => $viewer->id,
            ]);
        }

        $this->actingAs($viewer)->get(route('reports.index'))
            ->assertOk()
            ->assertViewHas('transactions', fn ($paginator): bool => $paginator->perPage() === 10 && $paginator->total() === 11);
    }
}
