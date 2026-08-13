<?php

namespace App\Services;

use App\Models\FuelEntry;
use App\Models\Tanker;
use App\Models\TankerMovement;
use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

class TankerStockService
{
    public function purchase(array $data, int $userId): TankerMovement
    {
        $tanker = Tanker::query()->lockForUpdate()->findOrFail($data['tanker_id']);
        $liters = round((float) $data['liters'], 3);
        $unitCost = round((float) $data['unit_cost'], 4);
        $oldStock = (float) $tanker->stock_liters;
        $newStock = $oldStock + $liters;

        $transaction = Transaction::create([
            'type' => 'expense', 'category' => 'Yakıt Alımı',
            'description' => $tanker->name.' stok yakıt alımı',
            'amount' => round($liters * $unitCost, 2), 'affects_cash' => true,
            'occurred_on' => $data['movement_date'], 'created_by' => $userId,
        ]);
        $tanker->update([
            'stock_liters' => $newStock,
            // Araç maliyetleri kullanıcının tercih ettiği son tanker alış fiyatıyla hesaplanır.
            'average_unit_cost' => $unitCost,
        ]);

        return TankerMovement::create([
            ...$data, 'type' => 'purchase', 'unit_cost' => $unitCost,
            'total_amount' => round($liters * $unitCost, 2), 'balance_after' => $newStock,
            'transaction_id' => $transaction->id, 'created_by' => $userId,
        ]);
    }

    public function issue(FuelEntry $fuel, int $tankerId, int $userId): TankerMovement
    {
        $tanker = Tanker::query()->lockForUpdate()->findOrFail($tankerId);
        $liters = round((float) $fuel->liters, 3);
        if ((float) $tanker->stock_liters < $liters) {
            throw ValidationException::withMessages([
                'liters' => $tanker->name.' stokunda yeterli yakıt yok. Mevcut stok: '
                    .number_format((float) $tanker->stock_liters, 3, ',', '.')
                    .' L. Önce Tanker Stokları > Tankere Yakıt Al bölümünden stok girişi yapın.',
            ]);
        }

        $unitCost = (float) $tanker->average_unit_cost;
        $balance = round((float) $tanker->stock_liters - $liters, 3);
        $fuel->update([
            'tanker_id' => $tanker->id,
            'unit_price' => $unitCost,
            'total_amount' => round($liters * $unitCost, 2),
        ]);
        $tanker->update(['stock_liters' => $balance]);

        return TankerMovement::create([
            'tanker_id' => $tanker->id, 'type' => 'issue',
            'movement_date' => $fuel->fuel_date, 'movement_time' => $fuel->fuel_time,
            'liters' => $liters, 'unit_cost' => $unitCost,
            'total_amount' => round($liters * $unitCost, 2), 'balance_after' => $balance,
            'fuel_entry_id' => $fuel->id, 'notes' => $fuel->notes, 'created_by' => $userId,
        ]);
    }

    public function reverseIssue(FuelEntry $fuel): void
    {
        $movement = TankerMovement::query()->where('fuel_entry_id', $fuel->id)->lockForUpdate()->first();
        if (! $movement || $movement->trashed()) {
            return;
        }

        $tanker = Tanker::query()->lockForUpdate()->findOrFail($movement->tanker_id);
        $tanker->update(['stock_liters' => round((float) $tanker->stock_liters + (float) $movement->liters, 3)]);
        $movement->delete();
    }

    public function syncIssueMetadata(FuelEntry $fuel): void
    {
        TankerMovement::query()->where('fuel_entry_id', $fuel->id)->update([
            'movement_date' => $fuel->fuel_date,
            'movement_time' => $fuel->fuel_time,
            'notes' => $fuel->notes,
        ]);
    }
}
