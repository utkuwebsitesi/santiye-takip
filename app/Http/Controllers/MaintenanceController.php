<?php

namespace App\Http\Controllers;

use App\Models\FuelEntry;
use App\Models\MaintenanceEntry;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\Services\AuditService;
use App\Services\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MaintenanceController extends Controller
{
    public function index(Request $request): View
    {
        $entries = MaintenanceEntry::with(['vehicle', 'creator'])
            ->when($request->filled('vehicle_id'), fn ($query) => $query->where('vehicle_id', $request->integer('vehicle_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('maintenance_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('maintenance_date', '<=', $request->date('to')))
            ->latest('maintenance_date')->paginate(25)->withQueryString();
        $currentMeters = FuelEntry::query()
            ->whereNotNull('meter_value')
            ->whereIn('vehicle_id', $entries->pluck('vehicle_id')->unique())
            ->groupBy('vehicle_id')
            ->selectRaw('vehicle_id, MAX(meter_value) as current_meter')
            ->get()
            ->pluck('current_meter', 'vehicle_id');
        $currentHours = FuelEntry::query()
            ->whereNotNull('operating_hours')
            ->whereIn('vehicle_id', $entries->pluck('vehicle_id')->unique())
            ->groupBy('vehicle_id')
            ->selectRaw('vehicle_id, MAX(operating_hours) as current_hours')
            ->get()
            ->pluck('current_hours', 'vehicle_id');

        return view('maintenance.index', [
            'entries' => $entries,
            'vehicles' => Vehicle::orderBy('name')->get(),
            'currentMeters' => $currentMeters,
            'currentHours' => $currentHours,
        ]);
    }

    public function create(): View
    {
        return view('maintenance.create', [
            'vehicles' => Vehicle::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit, DocumentService $documents): RedirectResponse
    {
        $data = $this->validated($request);
        $path = null;
        try {
            if ($request->hasFile('document')) {
                $path = $documents->store($request->file('document'), 'maintenance');
                $data['document_path'] = $path;
            }
            DB::transaction(function () use ($data, $request, $audit): void {
                $transaction = $this->syncTransaction(null, $data, $request);
                $entry = MaintenanceEntry::create($data + [
                    'transaction_id' => $transaction?->id,
                    'created_by' => $request->user()->id,
                ]);
                $audit->created($entry, 'Bakım veya onarım kaydı oluşturuldu.');
                if ($transaction) {
                    $audit->created($transaction, 'Bakım maliyeti kasa giderine kaydedildi.');
                }
            });
        } catch (\Throwable $e) {
            if ($path) {
                $documents->delete($path);
            }
            throw $e;
        }

        return redirect()->route('maintenance.index')->with('success', 'Bakım kaydı oluşturuldu.');
    }

    public function edit(MaintenanceEntry $maintenance): View
    {
        return view('maintenance.edit', [
            'maintenance' => $maintenance,
            'vehicles' => Vehicle::where('is_active', true)->orWhere('id', $maintenance->vehicle_id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, MaintenanceEntry $maintenance, AuditService $audit, DocumentService $documents): RedirectResponse
    {
        $data = $this->validated($request);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
        $newPath = null;
        if ($request->boolean('remove_document')) {
            $data['document_path'] = null;
        } elseif ($request->hasFile('document')) {
            $newPath = $documents->store($request->file('document'), 'maintenance');
            $data['document_path'] = $newPath;
        }
        $data['updated_by'] = $request->user()->id;
        try {
            DB::transaction(function () use ($maintenance, $data, $request, $audit, $reason): void {
                $transaction = $this->syncTransaction($maintenance->transaction, $data, $request, $audit, $reason);
                $data['transaction_id'] = $transaction?->id;
                $audit->update($maintenance, $data, $reason);
            });
        } catch (\Throwable $e) {
            if ($newPath) {
                $documents->delete($newPath);
            }
            throw $e;
        }

        return back()->with('success', 'Bakım kaydı güncellendi.');
    }

    public function destroy(Request $request, MaintenanceEntry $maintenance, AuditService $audit): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
        DB::transaction(function () use ($maintenance, $audit, $reason): void {
            if ($maintenance->transaction) {
                $audit->delete($maintenance->transaction, $reason);
            }
            $audit->delete($maintenance, $reason);
        });

        return back()->with('success', 'Bakım kaydı silindi ve geçmişe işlendi.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'vehicle_id' => [
                'required',
                Rule::exists('vehicles', 'id')->where(function ($query) use ($request): void {
                    $query->where('is_active', true);
                    if ($request->route('maintenance')) {
                        $query->orWhere('id', $request->route('maintenance')->vehicle_id);
                    }
                }),
            ],
            'maintenance_date' => ['required', 'date'],
            'maintenance_type' => ['required', 'string', 'max:100'],
            'service_provider' => ['nullable', 'string', 'max:150'],
            'cost' => ['required', 'numeric', 'gte:0', 'max:999999999999.99'],
            'meter_value' => ['nullable', 'numeric', 'gte:0'],
            'operating_hours' => ['nullable', 'numeric', 'gte:0'],
            'next_maintenance_date' => ['nullable', 'date', 'after_or_equal:maintenance_date'],
            'next_meter_value' => ['nullable', 'numeric', 'gte:0'],
            'next_operating_hours' => ['nullable', 'numeric', 'gte:0'],
            'description' => ['required', 'string', 'max:2000'],
            'record_as_expense' => ['nullable', 'boolean'],
            'document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.DocumentService::MAX_KILOBYTES],
            'remove_document' => ['nullable', 'boolean'],
        ]);

        if (! Vehicle::findOrFail($data['vehicle_id'])->tracks_meters) {
            $data['meter_value'] = null;
            $data['operating_hours'] = null;
            $data['next_meter_value'] = null;
            $data['next_operating_hours'] = null;
        }

        return $data;
    }

    private function syncTransaction(?Transaction $transaction, array $data, Request $request, ?AuditService $audit = null, ?string $reason = null): ?Transaction
    {
        if (! $request->boolean('record_as_expense') || (float) $data['cost'] <= 0) {
            if ($transaction && ! $transaction->trashed()) {
                $audit?->delete($transaction, $reason ?? 'Bakım gideri bağlantısı kaldırıldı.');
            }

            return null;
        }

        $values = [
            'type' => 'expense', 'category' => 'Bakım / Onarım',
            'description' => $data['maintenance_type'].' - '.$data['description'],
            'amount' => $data['cost'], 'occurred_on' => $data['maintenance_date'],
            'document_path' => $data['document_path'] ?? $transaction?->document_path,
            'updated_by' => $request->user()->id,
        ];
        if ($transaction) {
            if ($transaction->trashed()) {
                $transaction->restore();
            }
            $audit?->update($transaction, $values, $reason ?? 'Bakım gideri güncellendi.');

            return $transaction;
        }

        return Transaction::create($values + ['created_by' => $request->user()->id]);
    }
}
