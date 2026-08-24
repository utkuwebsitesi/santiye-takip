<?php

namespace App\Http\Controllers;

use App\Models\FuelEntry;
use App\Models\MaintenanceEntry;
use App\Models\Tanker;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\Vehicle;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\FuelService;
use App\Services\TankerStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransactionController extends Controller
{
    public function index(Request $request): View
    {
        $items = Transaction::with('creator')
            ->where('affects_cash', true)
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('occurred_on', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('occurred_on', '<=', $request->date('to')))
            ->latest('occurred_on')->latest('id')->paginate(25)->withQueryString();

        return view('transactions.index', compact('items'));
    }

    public function create(): View
    {
        return view('transactions.create', [
            'vehicles' => Vehicle::where('is_active', true)->orderBy('plate')->orderBy('name')->get(),
            'tankers' => Tanker::where('is_active', true)->orderBy('id')->get(),
            'categories' => TransactionCategory::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit, DocumentService $documents, FuelService $fuelService, TankerStockService $stock): RedirectResponse
    {
        $data = $this->validated($request);
        $fuelData = $this->fuelData($data, $request, $fuelService);
        $maintenanceData = $this->maintenanceData($data, $fuelData);
        $transactionData = $this->transactionData($data, $fuelData);
        $transactionData['created_by'] = $request->user()->id;
        $path = null;
        try {
            if ($request->hasFile('document')) {
                $path = $documents->store($request->file('document'), 'transactions');
                $transactionData['document_path'] = $path;
            }
            DB::transaction(function () use ($transactionData, $fuelData, $maintenanceData, $path, $request, $audit, $stock): void {
                $transaction = Transaction::create($transactionData);
                $audit->created($transaction, 'Kasa hareketi oluşturuldu.');
                if ($fuelData) {
                    $fuel = FuelEntry::create($fuelData + [
                        'transaction_id' => $transaction->id,
                        'receipt_path' => $path,
                        'created_by' => $request->user()->id,
                    ]);
                    $stock->issue($fuel, (int) $fuelData['tanker_id'], $request->user()->id);
                    $audit->created($fuel, 'Kasa giderinden araç yakıt kaydı oluşturuldu.');
                }
                if ($maintenanceData) {
                    $maintenance = MaintenanceEntry::create($maintenanceData + [
                        'transaction_id' => $transaction->id,
                        'document_path' => $transaction->document_path,
                        'created_by' => $request->user()->id,
                    ]);
                    $audit->created($maintenance, 'Kasa giderinden araç bakım veya onarım kaydı oluşturuldu.');
                }
            });
        } catch (\Throwable $e) {
            if ($path) {
                $documents->delete($path);
            }
            throw $e;
        }

        if ($fuelData) {
            return redirect()->route('fuel.index')->with(
                'success',
                'Yakıt kaydı oluşturuldu; araç takibine işlendi ve kasa bakiyesi değiştirilmedi.'
            );
        }

        if ($maintenanceData) {
            return redirect()->route('maintenance.index', ['vehicle_id' => $maintenanceData['vehicle_id']])->with(
                'success',
                'Kasa gideri kaydedildi; bakım veya onarım kaydı seçilen aracın geçmişine işlendi.'
            );
        }

        return redirect()->route('transactions.index')->with('success', 'Kasa hareketi kaydedildi ve kilitlendi.');
    }

    public function edit(Transaction $transaction): View
    {
        $transaction->load(['fuelEntry.vehicle', 'maintenanceEntry']);

        return view('transactions.edit', [
            'transaction' => $transaction,
            'vehicles' => Vehicle::where('is_active', true)
                ->when($transaction->fuelEntry, fn ($query) => $query->orWhere('id', $transaction->fuelEntry->vehicle_id))
                ->when($transaction->maintenanceEntry, fn ($query) => $query->orWhere('id', $transaction->maintenanceEntry->vehicle_id))
                ->orderBy('name')->get(),
            'tankers' => Tanker::where('is_active', true)
                ->when($transaction->fuelEntry, fn ($query) => $query->orWhere('id', $transaction->fuelEntry->tanker_id))
                ->orderBy('id')->get(),
            'categories' => TransactionCategory::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function update(Request $request, Transaction $transaction, AuditService $audit, DocumentService $documents, FuelService $fuelService, TankerStockService $stock): RedirectResponse
    {
        $data = $this->validated($request);
        $existingFuel = $transaction->fuelEntry()->withTrashed()->first();
        $existingMaintenance = $transaction->maintenanceEntry()->withTrashed()->first();
        $fuelData = $this->fuelData($data, $request, $fuelService, $existingFuel);
        $maintenanceData = $this->maintenanceData($data, $fuelData);
        if ($existingFuel && $fuelData) {
            if ((int) $fuelData['tanker_id'] !== (int) $existingFuel->tanker_id || (float) $fuelData['liters'] !== (float) $existingFuel->liters) {
                throw ValidationException::withMessages([
                    'liters' => 'Stok güvenliği için kaydedilmiş ikmalin tanker ve litre bilgisi değiştirilemez. Kaydı silip yeniden girin.',
                ]);
            }
            $fuelData['unit_price'] = $existingFuel->unit_price;
            $fuelData['total_amount'] = $existingFuel->total_amount;
        }
        $transactionData = $this->transactionData($data, $fuelData);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
        $newPath = null;
        if ($request->boolean('remove_document')) {
            $transactionData['document_path'] = null;
        } elseif ($request->hasFile('document')) {
            $newPath = $documents->store($request->file('document'), 'transactions');
            $transactionData['document_path'] = $newPath;
        }
        $transactionData['updated_by'] = $request->user()->id;
        try {
            DB::transaction(function () use ($transaction, $transactionData, $fuelData, $maintenanceData, $existingFuel, $existingMaintenance, $newPath, $request, $audit, $reason, $stock): void {
                $audit->update($transaction, $transactionData, $reason);
                if ($fuelData) {
                    $fuelData['receipt_path'] = array_key_exists('document_path', $transactionData)
                        ? $transactionData['document_path']
                        : ($newPath ?: $transaction->document_path);
                    $fuelData['updated_by'] = $request->user()->id;
                    if ($existingFuel) {
                        if ($existingFuel->trashed()) {
                            $existingFuel->restore();
                        }
                        $audit->update($existingFuel, $fuelData, $reason);
                        $stock->syncIssueMetadata($existingFuel->fresh());
                    } else {
                        $fuel = FuelEntry::create($fuelData + [
                            'transaction_id' => $transaction->id,
                            'created_by' => $request->user()->id,
                        ]);
                        $stock->issue($fuel, (int) $fuelData['tanker_id'], $request->user()->id);
                        $audit->created($fuel, $reason);
                    }
                } elseif ($existingFuel && ! $existingFuel->trashed()) {
                    $stock->reverseIssue($existingFuel);
                    $audit->delete($existingFuel, $reason);
                }
                if ($maintenanceData) {
                    $maintenanceValues = $maintenanceData + [
                        'transaction_id' => $transaction->id,
                        'document_path' => $transaction->document_path,
                        'updated_by' => $request->user()->id,
                    ];
                    if ($existingMaintenance) {
                        if ($existingMaintenance->trashed()) {
                            $existingMaintenance->restore();
                        }
                        $audit->update($existingMaintenance, $maintenanceValues, $reason);
                    } else {
                        $maintenance = MaintenanceEntry::create($maintenanceValues + [
                            'created_by' => $request->user()->id,
                        ]);
                        $audit->created($maintenance, $reason);
                    }
                } elseif ($existingMaintenance && ! $existingMaintenance->trashed()) {
                    $audit->delete($existingMaintenance, $reason);
                }
            });
        } catch (\Throwable $e) {
            if ($newPath) {
                $documents->delete($newPath);
            }
            throw $e;
        }

        return back()->with('success', 'Kayıt düzeltildi; eski ve yeni bilgiler geçmişe işlendi.');
    }

    public function destroy(Request $request, Transaction $transaction, AuditService $audit, TankerStockService $stock): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
        DB::transaction(function () use ($transaction, $audit, $reason, $stock): void {
            $fuel = $transaction->fuelEntry;
            $maintenance = $transaction->maintenanceEntry()->withTrashed()->first();
            if ($fuel) {
                $stock->reverseIssue($fuel);
                $audit->delete($fuel, $reason);
            }
            if ($maintenance && ! $maintenance->trashed()) {
                $audit->delete($maintenance, $reason);
            }
            $audit->delete($transaction, $reason);
        });

        return back()->with('success', 'Kayıt silindi ve denetim geçmişine taşındı.');
    }

    private function validated(Request $request): array
    {
        $existingMaintenanceVehicleId = $request->route('transaction') instanceof Transaction
            ? $request->route('transaction')->maintenanceEntry()->withTrashed()->value('vehicle_id')
            : null;

        return $request->validate([
            'type' => ['required', 'in:income,expense'],
            'is_fuel_expense' => ['nullable', 'boolean'],
            'category' => ['required_unless:is_fuel_expense,1', 'nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required_unless:is_fuel_expense,1', 'nullable', 'numeric', 'gt:0', 'max:999999999999.99'],
            'occurred_on' => ['required', 'date'],
            'vehicle_id' => ['required_if:is_fuel_expense,1', 'nullable', 'exists:vehicles,id,is_active,1'],
            'maintenance_vehicle_id' => [
                $this->isMaintenanceExpense($request) ? 'required' : 'nullable',
                Rule::exists('vehicles', 'id')->where(function ($query) use ($existingMaintenanceVehicleId): void {
                    $query->where('is_active', true);
                    if ($existingMaintenanceVehicleId !== null) {
                        $query->orWhere('id', $existingMaintenanceVehicleId);
                    }
                }),
            ],
            'maintenance_service_provider' => ['nullable', 'string', 'max:150'],
            'tanker_id' => ['required_if:is_fuel_expense,1', 'nullable', 'exists:tankers,id,is_active,1'],
            'fuel_time' => ['nullable', 'date_format:H:i'],
            'liters' => ['required_if:is_fuel_expense,1', 'nullable', 'numeric', 'gt:0', 'max:999999.999'],
            'unit_price' => ['nullable', 'numeric', 'gte:0', 'max:999999.999'],
            'meter_value' => ['nullable', 'numeric', 'gte:0'],
            'operating_hours' => ['nullable', 'numeric', 'gte:0'],
            'station' => ['nullable', 'string', 'max:150'],
            'fuel_notes' => ['nullable', 'string', 'max:1000'],
            'meter_override_reason' => ['nullable', 'string', 'min:5', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.DocumentService::MAX_KILOBYTES],
            'remove_document' => ['nullable', 'boolean'],
        ]);
    }

    private function fuelData(array $data, Request $request, FuelService $fuelService, ?FuelEntry $current = null): ?array
    {
        if (! $request->boolean('is_fuel_expense')) {
            return null;
        }

        abort_unless($data['type'] === 'expense', 422, 'Yakıt kaydı yalnızca gider işleminde oluşturulabilir.');
        $tanker = Tanker::findOrFail($data['tanker_id']);
        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        $fuel = [
            'vehicle_id' => $data['vehicle_id'],
            'tanker_id' => $tanker->id,
            'fuel_date' => $data['occurred_on'],
            'fuel_time' => $data['fuel_time'] ?? null,
            'liters' => $data['liters'],
            'unit_price' => $tanker->average_unit_cost,
            'total_amount' => $fuelService->calculateTotal((string) $data['liters'], (string) $tanker->average_unit_cost),
            'meter_value' => $vehicle->tracks_meters ? ($data['meter_value'] ?? null) : null,
            'operating_hours' => $vehicle->tracks_meters ? ($data['operating_hours'] ?? null) : null,
            'station' => $data['station'] ?? null,
            'notes' => $data['fuel_notes'] ?? null,
        ];
        $fuelService->validateMeterSequence($fuel, $request->user(), $current, $data['meter_override_reason'] ?? null);

        return $fuel;
    }

    private function maintenanceData(array $data, ?array $fuelData): ?array
    {
        if ($fuelData || ! $this->isMaintenanceExpense($data)) {
            return null;
        }

        return [
            'vehicle_id' => $data['maintenance_vehicle_id'],
            'maintenance_date' => $data['occurred_on'],
            'maintenance_type' => 'Bakım / Onarım',
            'service_provider' => $data['maintenance_service_provider'] ?? null,
            'cost' => $data['amount'],
            'description' => $data['description'],
        ];
    }

    private function isMaintenanceExpense(Request|array $source): bool
    {
        $data = $source instanceof Request ? $source->all() : $source;
        $category = mb_strtolower(trim((string) ($data['category'] ?? '')), 'UTF-8');

        return ($data['type'] ?? null) === 'expense'
            && str_contains($category, 'bakım')
            && str_contains($category, 'onarım');
    }

    private function transactionData(array $data, ?array $fuelData): array
    {
        return [
            'type' => $fuelData ? 'expense' : $data['type'],
            'category' => $fuelData ? 'Yakıt' : $data['category'],
            'description' => $data['description'],
            'amount' => $fuelData['total_amount'] ?? $data['amount'],
            'affects_cash' => ! $fuelData,
            'occurred_on' => $data['occurred_on'],
        ];
    }
}
