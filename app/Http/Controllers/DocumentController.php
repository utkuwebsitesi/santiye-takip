<?php

namespace App\Http\Controllers;

use App\Models\FuelEntry;
use App\Models\MaintenanceEntry;
use App\Models\Transaction;
use App\Services\DocumentService;

class DocumentController extends Controller
{
    public function transaction(Transaction $transaction, DocumentService $documents)
    {
        abort_unless($transaction->document_path, 404);
        $extension = pathinfo($transaction->document_path, PATHINFO_EXTENSION);

        return $documents->download($transaction->document_path, "kasa-belgesi-{$transaction->id}.{$extension}");
    }

    public function fuel(FuelEntry $fuelEntry, DocumentService $documents)
    {
        abort_unless($fuelEntry->receipt_path, 404);
        $extension = pathinfo($fuelEntry->receipt_path, PATHINFO_EXTENSION);

        return $documents->download($fuelEntry->receipt_path, "yakit-fisi-{$fuelEntry->id}.{$extension}");
    }

    public function maintenance(MaintenanceEntry $maintenance, DocumentService $documents)
    {
        abort_unless($maintenance->document_path, 404);
        $extension = pathinfo($maintenance->document_path, PATHINFO_EXTENSION);

        return $documents->download($maintenance->document_path, "bakim-belgesi-{$maintenance->id}.{$extension}");
    }
}
