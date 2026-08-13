<?php

namespace App\Http\Controllers;

use App\Models\FuelEntry;
use App\Models\Tanker;
use App\Models\Vehicle;
use App\Services\AuditService;
use App\Services\DocumentService;
use App\Services\FuelService;
use App\Services\TankerStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FuelEntryController extends Controller
{
    public function index(Request $request, FuelService $fuelService): View
    {
        $query = FuelEntry::with(['vehicle', 'tanker', 'creator'])
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('fuel_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('fuel_date', '<=', $request->date('to')));
        $items = (clone $query)->latest('fuel_date')->paginate(25)->withQueryString();
        $summaryRows = $request->filled('vehicle_id') ? (clone $query)->get() : collect();

        return view('fuel.index', [
            'items' => $items,
            'vehicles' => Vehicle::where('is_active', true)->orderBy('plate')->orderBy('name')->get(),
            'tankers' => Tanker::where('is_active', true)->orderBy('id')->get(),
            'selectedVehicle' => $request->filled('vehicle_id') ? Vehicle::find($request->integer('vehicle_id')) : null,
            'fuelSummary' => $request->filled('vehicle_id') ? [
                'liters' => $summaryRows->sum('liters'),
                'amount' => $summaryRows->sum('total_amount'),
                ...$fuelService->consumptionRates($summaryRows),
            ] : null,
        ]);
    }

    public function create(): View
    {
        return view('fuel.create', [
            'vehicles' => Vehicle::where('is_active', true)->orderBy('plate')->orderBy('name')->get(),
            'tankers' => Tanker::where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function store(Request $request, AuditService $audit, DocumentService $documents, FuelService $fuelService, TankerStockService $stock): RedirectResponse
    {
        $data = $this->validated($request, $fuelService);
        $auditReason = $data['meter_override_reason'] ?? 'Yakıt kaydı oluşturuldu.';
        unset($data['meter_override_reason']);
        $data['unit_price'] = Tanker::findOrFail($data['tanker_id'])->average_unit_cost;
        $data['created_by'] = $request->user()->id;
        $data['total_amount'] = $fuelService->calculateTotal((string) $data['liters'], (string) $data['unit_price']);
        $path = null;
        try {
            if ($request->hasFile('receipt')) {
                $path = $documents->store($request->file('receipt'), 'fuel');
                $data['receipt_path'] = $path;
            }
            DB::transaction(function () use ($data, $audit, $auditReason, $stock, $request): void {
                $tankerId = (int) $data['tanker_id'];
                $entry = FuelEntry::create($data);
                $stock->issue($entry, $tankerId, $request->user()->id);
                $audit->created($entry, $auditReason);
            });
        } catch (\Throwable $e) {
            if ($path) {
                $documents->delete($path);
            }
            throw $e;
        }

        return redirect()->route('fuel.index')->with('success', 'Yakıt kaydı kaydedildi ve kilitlendi.');
    }

    public function edit(FuelEntry $fuelEntry): View
    {
        return view('fuel.edit', [
            'fuelEntry' => $fuelEntry,
            'vehicles' => Vehicle::where('is_active', true)->orWhere('id', $fuelEntry->vehicle_id)->orderBy('name')->get(),
            'tankers' => Tanker::where('is_active', true)->orWhere('id', $fuelEntry->tanker_id)->orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, FuelEntry $fuelEntry, AuditService $audit, DocumentService $documents, FuelService $fuelService, TankerStockService $stock): RedirectResponse
    {
        $data = $this->validated($request, $fuelService, $fuelEntry);
        if ((int) $data['tanker_id'] !== (int) $fuelEntry->tanker_id || (float) $data['liters'] !== (float) $fuelEntry->liters) {
            throw ValidationException::withMessages([
                'liters' => 'Stok güvenliği için kaydedilmiş ikmalin tanker ve litre bilgisi değiştirilemez. Kaydı silip yeniden girin.',
            ]);
        }
        $data['unit_price'] = $fuelEntry->unit_price;
        unset($data['meter_override_reason']);
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
        $data['total_amount'] = $fuelService->calculateTotal((string) $data['liters'], (string) $data['unit_price']);
        $newPath = null;
        if ($request->boolean('remove_receipt')) {
            $data['receipt_path'] = null;
        } elseif ($request->hasFile('receipt')) {
            $newPath = $documents->store($request->file('receipt'), 'fuel');
            $data['receipt_path'] = $newPath;
        }
        $data['updated_by'] = $request->user()->id;
        try {
            DB::transaction(function () use ($audit, $fuelEntry, $data, $reason, $stock): void {
                $audit->update($fuelEntry, $data, $reason);
                $stock->syncIssueMetadata($fuelEntry->fresh());
            });
        } catch (\Throwable $e) {
            if ($newPath) {
                $documents->delete($newPath);
            }
            throw $e;
        }

        return back()->with('success', 'Yakıt kaydı düzeltildi ve değişiklik geçmişe işlendi.');
    }

    public function destroy(Request $request, FuelEntry $fuelEntry, AuditService $audit, TankerStockService $stock): RedirectResponse
    {
        $reason = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']])['reason'];
        DB::transaction(function () use ($fuelEntry, $reason, $audit, $stock): void {
            $stock->reverseIssue($fuelEntry);
            $audit->delete($fuelEntry, $reason);
        });

        return back()->with('success', 'Yakıt kaydı silindi ve denetim geçmişine taşındı.');
    }

    private function validated(Request $request, FuelService $fuelService, ?FuelEntry $current = null): array
    {
        $data = $request->validate([
            'vehicle_id' => ['required', 'exists:vehicles,id,is_active,1'],
            'tanker_id' => ['required', 'exists:tankers,id,is_active,1'],
            'fuel_date' => ['required', 'date'],
            'fuel_time' => ['nullable', 'date_format:H:i'],
            'liters' => ['required', 'numeric', 'gt:0', 'max:999999.999'],
            'unit_price' => ['nullable', 'numeric', 'gte:0', 'max:999999.999'],
            'meter_value' => ['nullable', 'numeric', 'gte:0'],
            'operating_hours' => ['nullable', 'numeric', 'gte:0'],
            'station' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.DocumentService::MAX_KILOBYTES],
            'remove_receipt' => ['nullable', 'boolean'],
            'meter_override_reason' => ['nullable', 'string', 'min:5', 'max:1000'],
        ]);

        if (! Vehicle::findOrFail($data['vehicle_id'])->tracks_meters) {
            $data['meter_value'] = null;
            $data['operating_hours'] = null;
        }
        $fuelService->validateMeterSequence($data, $request->user(), $current, $data['meter_override_reason'] ?? null);

        return $data;
    }
}
