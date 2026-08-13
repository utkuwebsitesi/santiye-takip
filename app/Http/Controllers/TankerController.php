<?php

namespace App\Http\Controllers;

use App\Models\Tanker;
use App\Models\TankerMovement;
use App\Services\AuditService;
use App\Services\TankerStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TankerController extends Controller
{
    public function index(): View
    {
        return view('tankers.index', [
            'tankers' => Tanker::where('is_active', true)->orderBy('id')->get(),
            'movements' => TankerMovement::with(['tanker', 'fuelEntry.vehicle', 'creator'])
                ->latest('movement_date')->latest('id')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('tankers.create', [
            'tankers' => Tanker::where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function manage(): View
    {
        return view('tankers.manage', [
            'tankers' => Tanker::query()
                ->withCount([
                    'movements as active_movement_count',
                    'fuelEntries as active_fuel_entry_count',
                    'movements as archived_movement_count' => fn ($query) => $query->onlyTrashed(),
                    'fuelEntries as archived_fuel_entry_count' => fn ($query) => $query->onlyTrashed(),
                ])
                ->orderByDesc('is_active')->orderBy('name')->get(),
        ]);
    }

    public function storeTanker(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('tankers', 'name')],
        ]);

        $tanker = Tanker::create([
            'name' => trim($data['name']),
            'stock_liters' => 0,
            'average_unit_cost' => 0,
            'is_active' => true,
        ]);
        $audit->created($tanker, 'Yeni tanker tanımı oluşturuldu.');

        return redirect()->route('tankers.manage')->with('success', 'Tanker eklendi. Yakıt alımı girildiğinde stok ve raporlara otomatik yansır.');
    }

    public function updateTanker(Request $request, Tanker $tanker, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('tankers', 'name')->ignore($tanker->id)],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $data['is_active'] && (float) $tanker->stock_liters > 0) {
            return back()->withErrors(['is_active' => 'Stok bulunan tanker pasife alınamaz. Önce stok hareketlerini kontrol edin.']);
        }

        if (! $data['is_active'] && Tanker::query()->where('is_active', true)->whereKeyNot($tanker->id)->doesntExist()) {
            return back()->withErrors(['is_active' => 'Sistemde en az bir aktif tanker bulunmalıdır.']);
        }

        $audit->update($tanker, [
            'name' => trim($data['name']),
            'is_active' => (bool) $data['is_active'],
        ], 'Tanker tanımı veya kullanım durumu güncellendi.');

        return redirect()->route('tankers.manage')->with('success', 'Tanker ayarları güncellendi.');
    }

    public function destroyTanker(Tanker $tanker, AuditService $audit): RedirectResponse
    {
        $history = $this->historyCounts($tanker);

        if ((float) $tanker->stock_liters > 0 || $this->hasActiveHistory($history)) {
            return back()->withErrors(['tanker' => 'Stok veya aktif hareketi bulunan tanker silinemez. Yönetim ekranında engel olan kayıtların sayısını görebilirsiniz.']);
        }

        if ($this->hasArchivedHistory($history)) {
            return back()->withErrors(['tanker' => 'Bu tankere ait arşivlenmiş kayıtlar var. Yönetim ekranındaki “Arşivi temizle ve sil” işlemini kullanın.']);
        }

        DB::transaction(function () use ($tanker, $audit): void {
            $audit->delete($tanker, 'Hareketi olmayan tanker tanımı silindi.');
        });

        return redirect()->route('tankers.manage')->with('success', 'Hareketi olmayan tanker silindi.');
    }

    public function purgeArchivedAndDestroyTanker(Tanker $tanker, AuditService $audit): RedirectResponse
    {
        $history = $this->historyCounts($tanker);

        if ((float) $tanker->stock_liters > 0 || $this->hasActiveHistory($history)) {
            return back()->withErrors(['tanker' => 'Stok veya aktif hareketi bulunan tanker silinemez.']);
        }

        if (! $this->hasArchivedHistory($history)) {
            return $this->destroyTanker($tanker, $audit);
        }

        DB::transaction(function () use ($tanker, $audit, $history): void {
            $tanker->movements()->onlyTrashed()->forceDelete();
            $tanker->fuelEntries()->onlyTrashed()->forceDelete();
            $audit->delete($tanker, sprintf(
                '%d arşiv hareketi ve %d arşiv yakıt kaydı kalıcı olarak temizlenerek tanker silindi.',
                $history['archived_movements'],
                $history['archived_fuel_entries'],
            ));
        });

        return redirect()->route('tankers.manage')->with('success', 'Arşiv kayıtları temizlendi ve tanker silindi.');
    }

    public function store(Request $request, TankerStockService $stock, AuditService $audit): RedirectResponse
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

        DB::transaction(function () use ($data, $request, $stock, $audit): void {
            $movement = $stock->purchase($data, $request->user()->id);
            $audit->created($movement->transaction, 'Tankere yakıt alımı kasa gideri oluşturdu.');
            $audit->created($movement, 'Tankere yakıt alımı yapıldı ve kasaya gider işlendi.');
        });

        return redirect()->route('tankers.index')->with('success', 'Yakıt tankere eklendi ve tutarı kasadan düşüldü.');
    }

    private function historyCounts(Tanker $tanker): array
    {
        return [
            'active_movements' => $tanker->movements()->count(),
            'active_fuel_entries' => $tanker->fuelEntries()->count(),
            'archived_movements' => $tanker->movements()->onlyTrashed()->count(),
            'archived_fuel_entries' => $tanker->fuelEntries()->onlyTrashed()->count(),
        ];
    }

    private function hasActiveHistory(array $history): bool
    {
        return $history['active_movements'] > 0 || $history['active_fuel_entries'] > 0;
    }

    private function hasArchivedHistory(array $history): bool
    {
        return $history['archived_movements'] > 0 || $history['archived_fuel_entries'] > 0;
    }
}
