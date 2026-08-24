<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\AuditLog;
use App\Models\FuelEntry;
use App\Models\MaintenanceEntry;
use App\Models\Permission;
use App\Models\SystemNotification;
use App\Models\Tanker;
use App\Models\TankerMovement;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditService;
use App\Services\DatabaseBackupService;
use App\Services\DocumentService;
use App\Services\FuelService;
use App\Services\MaintenanceReminderService;
use App\Services\TankerStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class MobileApiController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'user' => $this->userPayload($request->user()),
                'dashboard' => $this->dashboardPayload(),
                'lookups' => $this->lookupPayload(),
                'unread_notifications' => SystemNotification::query()
                    ->where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            ],
        ]);
    }

    public function dashboard(): JsonResponse
    {
        return response()->json(['data' => $this->dashboardPayload()]);
    }

    public function lookups(): JsonResponse
    {
        return response()->json(['data' => $this->lookupPayload()]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $items = Transaction::query()->with('creator')
            ->where('affects_cash', true)
            ->when($request->filled('type'), fn ($query) => $query->where('type', $request->string('type')->toString()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('occurred_on', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('occurred_on', '<=', $request->date('to')))
            ->latest('occurred_on')->latest('id')->paginate($this->perPage($request));

        return response()->json($this->paginated($items, fn (Transaction $item) => $this->transactionPayload($item)));
    }

    public function storeTransaction(Request $request, AuditService $audit, DocumentService $documents): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'occurred_on' => ['required', 'date'],
            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.DocumentService::MAX_KILOBYTES],
        ]);

        $path = null;
        try {
            if ($request->hasFile('document')) {
                $path = $documents->store($request->file('document'), 'transactions');
            }
            $transaction = DB::transaction(function () use ($data, $path, $request, $audit): Transaction {
                $entry = Transaction::create([
                    ...$data,
                    'document_path' => $path,
                    'affects_cash' => true,
                    'created_by' => $request->user()->id,
                ]);
                $audit->created($entry, 'Mobil uygulamadan kasa hareketi oluşturuldu.');

                return $entry;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                $documents->delete($path);
            }
            throw $exception;
        }

        return response()->json([
            'message' => 'Kasa hareketi kaydedildi.',
            'data' => $this->transactionPayload($transaction->load('creator')),
        ], 201);
    }

    public function updateTransaction(Request $request, Transaction $transaction, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999.99'],
            'occurred_on' => ['required', 'date'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $reason = $data['reason'];
        unset($data['reason']);
        $data['updated_by'] = $request->user()->id;
        $audit->update($transaction, $data, $reason);

        return response()->json(['message' => 'Kasa hareketi güncellendi.', 'data' => $this->transactionPayload($transaction->fresh('creator'))]);
    }

    public function destroyTransaction(Request $request, Transaction $transaction, AuditService $audit, TankerStockService $stock): JsonResponse
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

        return response()->json(['message' => 'Kasa hareketi ve varsa bağlantılı kayıtlar silindi.']);
    }

    public function fuel(Request $request, FuelService $fuelService): JsonResponse
    {
        $query = FuelEntry::query()->with(['vehicle', 'tanker', 'creator'])
            ->when($request->filled('vehicle_id'), fn ($query) => $query->where('vehicle_id', $request->integer('vehicle_id')))
            ->when($request->filled('tanker_id'), fn ($query) => $query->where('tanker_id', $request->integer('tanker_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('fuel_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('fuel_date', '<=', $request->date('to')));
        $items = (clone $query)->latest('fuel_date')->latest('id')->paginate($this->perPage($request));
        $summaryRows = $request->filled('vehicle_id') ? (clone $query)->get() : collect();

        $payload = $this->paginated($items, fn (FuelEntry $item) => $this->fuelPayload($item));
        $payload['summary'] = $request->filled('vehicle_id') ? [
            'liters' => (float) $summaryRows->sum('liters'),
            'amount' => (float) $summaryRows->sum('total_amount'),
            ...$fuelService->consumptionRates($summaryRows),
        ] : null;

        return response()->json($payload);
    }

    public function storeFuel(Request $request, AuditService $audit, DocumentService $documents, FuelService $fuelService, TankerStockService $stock): JsonResponse
    {
        $data = $this->validateFuel($request, $fuelService);
        $reason = $data['meter_override_reason'] ?? 'Mobil uygulamadan yakıt kaydı oluşturuldu.';
        unset($data['meter_override_reason']);
        $tanker = Tanker::query()->findOrFail($data['tanker_id']);
        $data['unit_price'] = $tanker->average_unit_cost;
        $data['total_amount'] = $fuelService->calculateTotal((string) $data['liters'], (string) $data['unit_price']);
        $path = null;
        try {
            if ($request->hasFile('receipt')) {
                $path = $documents->store($request->file('receipt'), 'fuel');
                $data['receipt_path'] = $path;
            }
            $entry = DB::transaction(function () use ($data, $request, $stock, $audit, $reason): FuelEntry {
                $fuel = FuelEntry::create($data + ['created_by' => $request->user()->id]);
                $stock->issue($fuel, (int) $data['tanker_id'], $request->user()->id);
                $audit->created($fuel, $reason);

                return $fuel;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                $documents->delete($path);
            }
            throw $exception;
        }

        return response()->json([
            'message' => 'Yakıt tanker stokundan düşülerek araca işlendi. Kasa bakiyesi etkilenmedi.',
            'data' => $this->fuelPayload($entry->fresh(['vehicle', 'tanker', 'creator'])),
        ], 201);
    }

    public function updateFuel(Request $request, FuelEntry $fuelEntry, AuditService $audit, FuelService $fuelService, TankerStockService $stock): JsonResponse
    {
        $data = $this->validateFuel($request, $fuelService, $fuelEntry);
        if ((int) $data['tanker_id'] !== (int) $fuelEntry->tanker_id || (float) $data['liters'] !== (float) $fuelEntry->liters) {
            throw ValidationException::withMessages([
                'liters' => 'Stok güvenliği için tanker ve litre değiştirilemez. Kaydı silip yeniden girin.',
            ]);
        }
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
        unset($data['meter_override_reason']);
        $data['unit_price'] = $fuelEntry->unit_price;
        $data['total_amount'] = $fuelService->calculateTotal((string) $data['liters'], (string) $data['unit_price']);
        $data['updated_by'] = $request->user()->id;

        DB::transaction(function () use ($fuelEntry, $data, $reason, $audit, $stock): void {
            $audit->update($fuelEntry, $data, $reason);
            $stock->syncIssueMetadata($fuelEntry->fresh());
        });

        return response()->json(['message' => 'Yakıt kaydı güncellendi.', 'data' => $this->fuelPayload($fuelEntry->fresh(['vehicle', 'tanker', 'creator']))]);
    }

    public function destroyFuel(Request $request, FuelEntry $fuelEntry, AuditService $audit, TankerStockService $stock): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
        DB::transaction(function () use ($fuelEntry, $audit, $stock, $reason): void {
            $stock->reverseIssue($fuelEntry);
            $audit->delete($fuelEntry, $reason);
        });

        return response()->json(['message' => 'Yakıt kaydı silindi; tanker stoğu geri eklendi.']);
    }

    public function tankers(): JsonResponse
    {
        $tankers = Tanker::query()->orderByDesc('is_active')->orderBy('name')->get();

        return response()->json(['data' => $tankers->map(fn (Tanker $tanker) => $this->tankerPayload($tanker))->values()]);
    }

    public function tankerMovements(Request $request): JsonResponse
    {
        $items = TankerMovement::query()->with(['tanker', 'fuelEntry.vehicle', 'creator'])
            ->when($request->filled('tanker_id'), fn ($query) => $query->where('tanker_id', $request->integer('tanker_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('movement_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('movement_date', '<=', $request->date('to')))
            ->latest('movement_date')->latest('id')->paginate($this->perPage($request));

        return response()->json($this->paginated($items, fn (TankerMovement $item) => [
            'id' => $item->id,
            'type' => $item->type,
            'movement_date' => $item->movement_date?->toDateString(),
            'movement_time' => $item->movement_time,
            'liters' => (float) $item->liters,
            'unit_cost' => (float) $item->unit_cost,
            'total_amount' => (float) $item->total_amount,
            'balance_after' => (float) $item->balance_after,
            'supplier' => $item->supplier,
            'notes' => $item->notes,
            'tanker' => $item->tanker ? $this->tankerPayload($item->tanker) : null,
            'vehicle' => $item->fuelEntry?->vehicle ? $this->vehiclePayload($item->fuelEntry->vehicle) : null,
            'creator' => $item->creator?->name,
        ]));
    }

    public function storeTankerPurchase(Request $request, TankerStockService $stock, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'tanker_id' => ['required', 'exists:tankers,id,is_active,1'],
            'movement_date' => ['required', 'date'],
            'movement_time' => ['nullable', 'date_format:H:i'],
            'liters' => ['required', 'numeric', 'gt:0', 'max:999999.999'],
            'unit_cost' => ['required', 'numeric', 'gt:0', 'max:999999.9999'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        $movement = DB::transaction(function () use ($data, $request, $stock, $audit): TankerMovement {
            $entry = $stock->purchase($data, $request->user()->id);
            $audit->created($entry->transaction, 'Mobil uygulamadan tankere yakıt alımı kasa gideri oluşturdu.');
            $audit->created($entry, 'Mobil uygulamadan tanker yakıt alımı yapıldı.');

            return $entry;
        });

        return response()->json(['message' => 'Yakıt tankere eklendi; tutarı kasadan düşüldü.', 'data' => ['id' => $movement->id]], 201);
    }

    public function storeTanker(Request $request, AuditService $audit): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('tankers', 'name')]]);
        $tanker = Tanker::create(['name' => trim($data['name']), 'stock_liters' => 0, 'average_unit_cost' => 0, 'is_active' => true]);
        $audit->created($tanker, 'Mobil uygulamadan yeni tanker tanımı oluşturuldu.');

        return response()->json(['message' => 'Tanker eklendi.', 'data' => $this->tankerPayload($tanker)], 201);
    }

    public function updateTanker(Request $request, Tanker $tanker, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('tankers', 'name')->ignore($tanker)],
            'is_active' => ['required', 'boolean'],
        ]);
        if (! $data['is_active'] && (float) $tanker->stock_liters > 0) {
            throw ValidationException::withMessages(['is_active' => 'Stok bulunan tanker pasife alınamaz.']);
        }
        if (! $data['is_active'] && Tanker::query()->where('is_active', true)->whereKeyNot($tanker->id)->doesntExist()) {
            throw ValidationException::withMessages(['is_active' => 'Sistemde en az bir aktif tanker bulunmalıdır.']);
        }
        $audit->update($tanker, ['name' => trim($data['name']), 'is_active' => (bool) $data['is_active']], 'Mobil uygulamadan tanker ayarı güncellendi.');

        return response()->json(['message' => 'Tanker güncellendi.', 'data' => $this->tankerPayload($tanker->fresh())]);
    }

    public function destroyTanker(Tanker $tanker, AuditService $audit): JsonResponse
    {
        $activeMovements = $tanker->movements()->count();
        $activeFuel = $tanker->fuelEntries()->count();
        $archivedMovements = $tanker->movements()->onlyTrashed()->count();
        $archivedFuel = $tanker->fuelEntries()->onlyTrashed()->count();
        if ((float) $tanker->stock_liters > 0 || $activeMovements || $activeFuel || $archivedMovements || $archivedFuel) {
            return response()->json([
                'message' => 'Tanker silinemiyor; stok veya geçmiş kayıtları korunuyor.',
                'blockers' => compact('activeMovements', 'activeFuel', 'archivedMovements', 'archivedFuel'),
            ], 422);
        }
        $audit->delete($tanker, 'Mobil uygulamadan hareketi olmayan tanker silindi.');

        return response()->json(['message' => 'Tanker silindi.']);
    }

    public function vehicles(Request $request): JsonResponse
    {
        $items = Vehicle::query()->when($request->boolean('active_only'), fn ($query) => $query->where('is_active', true))
            ->orderByDesc('is_active')->orderBy('name')->get();

        return response()->json(['data' => $items->map(fn (Vehicle $item) => $this->vehiclePayload($item))->values()]);
    }

    public function storeVehicle(Request $request, AuditService $audit): JsonResponse
    {
        $data = $this->validateVehicle($request);
        $vehicle = Vehicle::create($data);
        $audit->created($vehicle, 'Mobil uygulamadan araç veya makine tanımı oluşturuldu.');

        return response()->json(['message' => 'Araç veya makine eklendi.', 'data' => $this->vehiclePayload($vehicle)], 201);
    }

    public function updateVehicle(Request $request, Vehicle $vehicle, AuditService $audit): JsonResponse
    {
        $data = $this->validateVehicle($request, $vehicle);
        $audit->update($vehicle, $data, 'Mobil uygulamadan araç veya makine tanımı güncellendi.');

        return response()->json(['message' => 'Araç veya makine güncellendi.', 'data' => $this->vehiclePayload($vehicle->fresh())]);
    }

    public function destroyVehicle(Vehicle $vehicle, AuditService $audit): JsonResponse
    {
        if ($vehicle->fuelEntries()->exists() || $vehicle->maintenanceEntries()->exists()) {
            throw ValidationException::withMessages(['vehicle' => 'Geçmiş kaydı bulunan araç silinemez; pasif yapın.']);
        }
        $audit->delete($vehicle, 'Mobil uygulamadan kullanılmamış araç veya makine silindi.');

        return response()->json(['message' => 'Araç veya makine silindi.']);
    }

    public function maintenance(Request $request): JsonResponse
    {
        $items = MaintenanceEntry::query()->with(['vehicle', 'creator'])
            ->when($request->filled('vehicle_id'), fn ($query) => $query->where('vehicle_id', $request->integer('vehicle_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('maintenance_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('maintenance_date', '<=', $request->date('to')))
            ->latest('maintenance_date')->latest('id')->paginate($this->perPage($request));

        return response()->json($this->paginated($items, fn (MaintenanceEntry $item) => $this->maintenancePayload($item)));
    }

    public function storeMaintenance(Request $request, AuditService $audit, DocumentService $documents): JsonResponse
    {
        $data = $this->validateMaintenance($request);
        $path = null;
        try {
            if ($request->hasFile('document')) {
                $path = $documents->store($request->file('document'), 'maintenance');
                $data['document_path'] = $path;
            }
            $entry = DB::transaction(function () use ($data, $request, $audit): MaintenanceEntry {
                $transaction = $this->syncMaintenanceTransaction(null, $data, $request, $audit);
                $maintenance = MaintenanceEntry::create($data + [
                    'transaction_id' => $transaction?->id,
                    'created_by' => $request->user()->id,
                ]);
                $audit->created($maintenance, 'Mobil uygulamadan bakım veya onarım kaydı oluşturuldu.');

                return $maintenance;
            });
        } catch (\Throwable $exception) {
            if ($path) {
                $documents->delete($path);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Bakım kaydı oluşturuldu.', 'data' => $this->maintenancePayload($entry->fresh(['vehicle', 'creator']))], 201);
    }

    public function updateMaintenance(Request $request, MaintenanceEntry $maintenance, AuditService $audit): JsonResponse
    {
        $data = $this->validateMaintenance($request, $maintenance);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
        $data['updated_by'] = $request->user()->id;
        DB::transaction(function () use ($maintenance, $data, $request, $audit, $reason): void {
            $transaction = $this->syncMaintenanceTransaction($maintenance->transaction, $data, $request, $audit, $reason);
            $data['transaction_id'] = $transaction?->id;
            $audit->update($maintenance, $data, $reason);
        });

        return response()->json(['message' => 'Bakım kaydı güncellendi.', 'data' => $this->maintenancePayload($maintenance->fresh(['vehicle', 'creator']))]);
    }

    public function destroyMaintenance(Request $request, MaintenanceEntry $maintenance, AuditService $audit): JsonResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
        DB::transaction(function () use ($maintenance, $audit, $reason): void {
            if ($maintenance->transaction && ! $maintenance->transaction->trashed()) {
                $audit->delete($maintenance->transaction, $reason);
            }
            $audit->delete($maintenance, $reason);
        });

        return response()->json(['message' => 'Bakım kaydı silindi.']);
    }

    public function reports(Request $request, FuelService $fuelService): JsonResponse
    {
        $fuel = FuelEntry::query()->with(['vehicle', 'tanker'])
            ->when($request->filled('from'), fn ($query) => $query->whereDate('fuel_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('fuel_date', '<=', $request->date('to')))
            ->when($request->filled('vehicle_id'), fn ($query) => $query->where('vehicle_id', $request->integer('vehicle_id')))
            ->get();
        $cash = Transaction::query()->where('affects_cash', true)
            ->when($request->filled('from'), fn ($query) => $query->whereDate('occurred_on', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('occurred_on', '<=', $request->date('to')))
            ->get();

        return response()->json(['data' => [
            'cash' => [
                'income' => (float) $cash->where('type', 'income')->sum('amount'),
                'expense' => (float) $cash->where('type', 'expense')->sum('amount'),
                'net' => (float) $cash->where('type', 'income')->sum('amount') - (float) $cash->where('type', 'expense')->sum('amount'),
            ],
            'fuel' => [
                'liters' => (float) $fuel->sum('liters'),
                'amount' => (float) $fuel->sum('total_amount'),
                'daily' => $fuel->groupBy(fn (FuelEntry $entry) => $entry->fuel_date->toDateString())->map(fn ($rows, $day) => [
                    'period' => $day, 'liters' => (float) $rows->sum('liters'), 'amount' => (float) $rows->sum('total_amount'),
                ])->sortKeysDesc()->values(),
                'efficiency' => $fuel->groupBy('vehicle_id')->map(function ($rows) use ($fuelService): array {
                    return ['vehicle' => $this->vehiclePayload($rows->first()->vehicle), ...$fuelService->consumptionRates($rows)];
                })->values(),
            ],
        ]]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $items = SystemNotification::query()->where('user_id', $request->user()->id)->latest()->paginate($this->perPage($request));

        return response()->json($this->paginated($items, fn (SystemNotification $item) => [
            'id' => $item->id, 'title' => $item->title, 'message' => $item->message, 'link' => $item->link,
            'read_at' => $item->read_at?->toIso8601String(), 'created_at' => $item->created_at?->toIso8601String(),
        ]));
    }

    public function readAllNotifications(Request $request): JsonResponse
    {
        SystemNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'Tüm bildirimler okundu olarak işaretlendi.']);
    }

    public function changePassword(Request $request, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ], ['password.confirmed' => 'Yeni parola tekrarı eşleşmiyor.']);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mevcut parola yanlış.']);
        }
        $request->user()->update(['password' => Hash::make($data['password'])]);
        $audit->event($request->user(), 'password_changed', null, ['user_id' => $request->user()->id], 'Mobil uygulamadan parola değiştirildi.');

        return response()->json(['message' => 'Parolanız değiştirildi.']);
    }

    public function users(Request $request): JsonResponse
    {
        $query = User::query()->orderByDesc('is_active')->orderBy('name');
        if (! $request->user()->isSuperAdmin()) {
            $query->where('role', '!=', 'super_admin');
        }
        return response()->json(['data' => $query->get()->map(fn (User $user) => $this->userPayload($user))->values()]);
    }

    public function storeUser(Request $request, AuditService $audit): JsonResponse
    {
        $data = $this->validateUser($request);
        if ($data['role'] === 'super_admin' && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Sistem yöneticisi oluşturma yetkiniz yok.');
        }
        $user = User::create($data);
        $audit->created($user, 'Mobil uygulamadan kullanıcı oluşturuldu.');

        return response()->json(['message' => 'Kullanıcı oluşturuldu.', 'data' => $this->userPayload($user)], 201);
    }

    public function updateUser(Request $request, User $user, AuditService $audit): JsonResponse
    {
        if ($user->isSuperAdmin() && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Sistem yöneticisi hesabı değiştirilemez.');
        }
        $data = $this->validateUser($request, $user);
        if ($data['role'] === 'super_admin' && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Sistem yöneticisi yetkisi verilemez.');
        }
        if ($request->user()->is($user) && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => 'Kendi hesabınızı pasifleştiremezsiniz.']);
        }
        if ($user->isSuperAdmin() && (! $data['is_active'] || $data['role'] !== 'super_admin')
            && ! User::query()->whereKeyNot($user->id)->where('role', 'super_admin')->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['role' => 'Son aktif sistem yöneticisi değiştirilemez.']);
        }
        if ($user->role === 'admin' && (! $data['is_active'] || $data['role'] !== 'admin')
            && ! User::query()->whereKeyNot($user->id)->where('role', 'admin')->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['role' => 'Son aktif yönetici değiştirilemez.']);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $audit->update($user, $data, 'Mobil uygulamadan kullanıcı bilgileri güncellendi.');

        return response()->json(['message' => 'Kullanıcı güncellendi.', 'data' => $this->userPayload($user->fresh())]);
    }

    public function audit(Request $request): JsonResponse
    {
        $items = AuditLog::query()->with('user')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest('created_at')->paginate($this->perPage($request));
        return response()->json($this->paginated($items, fn (AuditLog $item) => [
            'id' => $item->id, 'event' => $item->event, 'reason' => $item->reason,
            'record_type' => class_basename($item->auditable_type), 'record_id' => $item->auditable_id,
            'user' => $item->user?->name, 'created_at' => $item->created_at?->toIso8601String(),
        ]));
    }

    public function system(): JsonResponse
    {
        $backups = collect(File::glob(config('backup.directory').DIRECTORY_SEPARATOR.'santiye360-*.sql.gz') ?: [])
            ->map(fn (string $path) => ['filename' => basename($path), 'size' => File::size($path), 'created_at' => File::lastModified($path)])
            ->sortByDesc('created_at')->take(10)->values();
        return response()->json(['data' => [
            'settings' => AppSetting::query()->pluck('value', 'key'),
            'categories' => TransactionCategory::query()->orderBy('type')->orderBy('sort_order')->get(),
            'backups' => $backups,
        ]]);
    }

    public function updateSettings(Request $request, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'software_name' => ['required', 'string', 'max:60'],
            'software_tagline' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:150'],
        ]);
        foreach ($data as $key => $value) {
            $setting = AppSetting::firstOrCreate(['key' => $key], ['value' => trim($value)]);
            $audit->update($setting, ['value' => trim($value)], 'Mobil uygulamadan sistem ayarı güncellendi.');
        }

        return response()->json(['message' => 'Sistem ayarları güncellendi.']);
    }

    public function storeCategory(Request $request, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'name' => ['required', 'string', 'max:100', Rule::unique('transaction_categories')->where('type', $request->input('type'))],
        ]);
        $category = TransactionCategory::create($data + [
            'sort_order' => ((int) TransactionCategory::query()->where('type', $data['type'])->max('sort_order')) + 10,
            'is_active' => true,
        ]);
        $audit->created($category, 'Mobil uygulamadan işlem kategorisi oluşturuldu.');

        return response()->json(['message' => 'Kategori eklendi.', 'data' => $category], 201);
    }

    public function updateCategory(Request $request, TransactionCategory $category, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('transaction_categories')->where('type', $category->type)->ignore($category)],
            'is_active' => ['required', 'boolean'],
        ]);
        $audit->update($category, $data, 'Mobil uygulamadan işlem kategorisi güncellendi.');

        return response()->json(['message' => 'Kategori güncellendi.', 'data' => $category->fresh()]);
    }

    public function createBackup(DatabaseBackupService $backups): JsonResponse
    {
        try {
            $path = $backups->create();
        } catch (\Throwable $exception) {
            report($exception);
            return response()->json(['message' => 'Yedek oluşturulamadı. Hosting yazma izinlerini kontrol edin.'], 422);
        }
        return response()->json(['message' => 'Veritabanı yedeği oluşturuldu.', 'data' => ['filename' => basename($path)]]);
    }

    /** @return array<string, mixed> */
    private function dashboardPayload(): array
    {
        $today = now(config('app.timezone'))->toDateString();
        $income = (float) Transaction::query()->where('affects_cash', true)->where('type', 'income')->sum('amount');
        $expense = (float) Transaction::query()->where('affects_cash', true)->where('type', 'expense')->sum('amount');
        $tankers = Tanker::query()->where('is_active', true)->orderBy('name')->get();
        $recentFuel = FuelEntry::query()->with(['vehicle', 'tanker'])->latest('fuel_date')->latest('id')->take(6)->get();

        return [
            'metrics' => [
                'cash_balance' => $income - $expense,
                'today_expense' => (float) Transaction::query()->where('affects_cash', true)->where('type', 'expense')->whereDate('occurred_on', $today)->sum('amount'),
                'fuel_liters' => (float) FuelEntry::query()->sum('liters'),
                'active_vehicle_count' => Vehicle::query()->where('is_active', true)->count(),
            ],
            'tankers' => $tankers->map(fn (Tanker $tanker) => $this->tankerPayload($tanker))->values(),
            'recent_fuel' => $recentFuel->map(fn (FuelEntry $fuel) => $this->fuelPayload($fuel))->values(),
            'maintenance_alerts' => app(MaintenanceReminderService::class)->due()->take(6)->map(fn (MaintenanceEntry $entry) => [
                'id' => $entry->id,
                'vehicle' => $this->vehiclePayload($entry->vehicle),
                'maintenance_type' => $entry->maintenance_type,
                'reasons' => $entry->reminder_reasons->values(),
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function lookupPayload(): array
    {
        return [
            'vehicles' => Vehicle::query()->where('is_active', true)->orderBy('name')->get()->map(fn (Vehicle $vehicle) => $this->vehiclePayload($vehicle))->values(),
            'tankers' => Tanker::query()->where('is_active', true)->orderBy('name')->get()->map(fn (Tanker $tanker) => $this->tankerPayload($tanker))->values(),
            'categories' => TransactionCategory::query()->where('is_active', true)->orderBy('type')->orderBy('sort_order')->get()->map(fn (TransactionCategory $category) => [
                'id' => $category->id, 'type' => $category->type, 'name' => $category->name,
            ])->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function transactionPayload(Transaction $item): array
    {
        return [
            'id' => $item->id, 'type' => $item->type, 'category' => $item->category, 'description' => $item->description,
            'amount' => (float) $item->amount, 'occurred_on' => $item->occurred_on?->toDateString(),
            'affects_cash' => $item->affects_cash, 'creator' => $item->creator?->name,
            'created_at' => $item->created_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function fuelPayload(FuelEntry $item): array
    {
        return [
            'id' => $item->id, 'fuel_date' => $item->fuel_date?->toDateString(), 'fuel_time' => $item->fuel_time,
            'liters' => (float) $item->liters, 'unit_price' => (float) $item->unit_price, 'total_amount' => (float) $item->total_amount,
            'meter_value' => $item->meter_value === null ? null : (float) $item->meter_value,
            'operating_hours' => $item->operating_hours === null ? null : (float) $item->operating_hours,
            'station' => $item->station, 'notes' => $item->notes, 'creator' => $item->creator?->name,
            'vehicle' => $item->vehicle ? $this->vehiclePayload($item->vehicle) : null,
            'tanker' => $item->tanker ? $this->tankerPayload($item->tanker) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function tankerPayload(Tanker $item): array
    {
        return [
            'id' => $item->id, 'name' => $item->name, 'stock_liters' => (float) $item->stock_liters,
            'last_unit_cost' => (float) $item->average_unit_cost, 'is_active' => $item->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function vehiclePayload(Vehicle $item): array
    {
        return [
            'id' => $item->id, 'type' => $item->type, 'name' => $item->name, 'plate' => $item->plate, 'code' => $item->code,
            'display_name' => $item->display_name, 'tracks_meters' => $item->tracks_meters, 'is_active' => $item->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function maintenancePayload(MaintenanceEntry $item): array
    {
        return [
            'id' => $item->id, 'maintenance_date' => $item->maintenance_date?->toDateString(), 'maintenance_type' => $item->maintenance_type,
            'service_provider' => $item->service_provider, 'cost' => (float) $item->cost, 'description' => $item->description,
            'next_maintenance_date' => $item->next_maintenance_date?->toDateString(),
            'next_meter_value' => $item->next_meter_value === null ? null : (float) $item->next_meter_value,
            'next_operating_hours' => $item->next_operating_hours === null ? null : (float) $item->next_operating_hours,
            'vehicle' => $item->vehicle ? $this->vehiclePayload($item->vehicle) : null, 'creator' => $item->creator?->name,
        ];
    }

    /** @return array<string, mixed> */
    private function userPayload(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'role' => $user->role, 'is_active' => $user->is_active,
            'is_admin' => $user->isAdmin(), 'is_super_admin' => $user->isSuperAdmin(),
            'permissions' => $user->isSuperAdmin() ? array_keys(Permission::catalog()) : $user->permissions()->pluck('key')->values()->all()];
    }

    private function perPage(Request $request): int
    {
        return min(100, max(1, (int) $request->input('per_page', 30)));
    }

    /** @return array<string, mixed> */
    private function paginated($paginator, callable $transform): array
    {
        return [
            'data' => $paginator->getCollection()->map($transform)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(), 'total' => $paginator->total(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function validateFuel(Request $request, FuelService $fuelService, ?FuelEntry $current = null): array
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id,is_active,1'], 'tanker_id' => ['required', 'exists:tankers,id,is_active,1'],
            'fuel_date' => ['required', 'date'], 'fuel_time' => ['nullable', 'date_format:H:i'],
            'liters' => ['required', 'numeric', 'gt:0', 'max:999999.999'], 'meter_value' => ['nullable', 'numeric', 'gte:0'],
            'operating_hours' => ['nullable', 'numeric', 'gte:0'], 'station' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'], 'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.DocumentService::MAX_KILOBYTES],
            'meter_override_reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ]);
        if (! Vehicle::query()->findOrFail($data['vehicle_id'])->tracks_meters) {
            $data['meter_value'] = null;
            $data['operating_hours'] = null;
        }
        $fuelService->validateMeterSequence($data, $request->user(), $current, $data['meter_override_reason'] ?? null);

        return $data;
    }

    /** @return array<string, mixed> */
    private function validateVehicle(Request $request, ?Vehicle $vehicle = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['vehicle', 'machine'])], 'name' => ['required', 'string', 'max:150'],
            'plate' => ['nullable', 'string', 'max:20', Rule::unique('vehicles', 'plate')->ignore($vehicle)],
            'code' => ['nullable', 'string', 'max:50', Rule::unique('vehicles', 'code')->ignore($vehicle)],
            'is_active' => ['required', 'boolean'], 'tracks_meters' => ['required', 'boolean'],
        ]);
        if ($data['type'] === 'vehicle' && blank($data['plate'])) {
            throw ValidationException::withMessages(['plate' => 'Araç için plaka zorunludur.']);
        }
        if ($data['type'] === 'machine' && blank($data['code'])) {
            throw ValidationException::withMessages(['code' => 'Makine için makine kodu zorunludur.']);
        }
        $data['plate'] = $data['type'] === 'vehicle' ? mb_strtoupper(trim((string) $data['plate']), 'UTF-8') : null;
        $data['code'] = $data['type'] === 'machine' ? mb_strtoupper(trim((string) $data['code']), 'UTF-8') : null;
        $data['tracking_unit'] = $vehicle?->tracking_unit ?? 'km';

        return $data;
    }

    /** @return array<string, mixed> */
    private function validateMaintenance(Request $request, ?MaintenanceEntry $current = null): array
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id'], 'maintenance_date' => ['required', 'date'],
            'maintenance_type' => ['required', 'string', 'max:100'], 'service_provider' => ['nullable', 'string', 'max:150'],
            'cost' => ['required', 'numeric', 'gte:0', 'max:999999999999.99'], 'meter_value' => ['nullable', 'numeric', 'gte:0'],
            'operating_hours' => ['nullable', 'numeric', 'gte:0'], 'next_maintenance_date' => ['nullable', 'date', 'after_or_equal:maintenance_date'],
            'next_meter_value' => ['nullable', 'numeric', 'gte:0'], 'next_operating_hours' => ['nullable', 'numeric', 'gte:0'],
            'description' => ['required', 'string', 'max:2000'], 'record_as_expense' => ['nullable', 'boolean'],
            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.DocumentService::MAX_KILOBYTES],
        ]);
        $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
        if (! $vehicle->is_active && (! $current || $current->vehicle_id !== $vehicle->id)) {
            throw ValidationException::withMessages(['vehicle_id' => 'Pasif araç için yeni bakım kaydı oluşturulamaz.']);
        }
        if (! $vehicle->tracks_meters) {
            $data['meter_value'] = null; $data['operating_hours'] = null; $data['next_meter_value'] = null; $data['next_operating_hours'] = null;
        }

        return $data;
    }

    private function syncMaintenanceTransaction(?Transaction $transaction, array $data, Request $request, ?AuditService $audit = null, ?string $reason = null): ?Transaction
    {
        if (! $request->boolean('record_as_expense') || (float) $data['cost'] <= 0) {
            if ($transaction && ! $transaction->trashed()) {
                $audit?->delete($transaction, $reason ?? 'Bakım gideri bağlantısı mobil uygulamadan kaldırıldı.');
            }
            return null;
        }
        $values = [
            'type' => 'expense', 'category' => 'Bakım / Onarım', 'description' => $data['maintenance_type'].' - '.$data['description'],
            'amount' => $data['cost'], 'occurred_on' => $data['maintenance_date'],
            'document_path' => $data['document_path'] ?? $transaction?->document_path, 'updated_by' => $request->user()->id,
        ];
        if ($transaction) {
            if ($transaction->trashed()) {
                $transaction->restore();
            }
            return $audit?->update($transaction, $values, $reason ?? 'Bakım gideri mobil uygulamadan güncellendi.') ?? $transaction;
        }
        $created = Transaction::create($values + ['created_by' => $request->user()->id]);
        $audit?->created($created, 'Mobil uygulamadan bakım gideri kaydedildi.');

        return $created;
    }

    /** @return array<string, mixed> */
    private function validateUser(Request $request, ?User $user = null): array
    {
        $passwordRule = $user ? ['nullable', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()] : ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()];
        return $request->validate([
            'name' => ['required', 'string', 'max:150'], 'username' => ['required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user)],
            'role' => ['required', Rule::in(['personnel', 'admin', 'super_admin'])], 'is_active' => ['required', 'boolean'], 'password' => $passwordRule,
        ]);
    }
}
