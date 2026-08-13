<?php

namespace App\Http\Controllers;

use App\Http\Requests\VehicleRequest;
use App\Models\Vehicle;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VehicleController extends Controller
{
    public function index(): View
    {
        return view('vehicles.index', ['vehicles' => Vehicle::orderByDesc('is_active')->orderBy('name')->paginate(25)]);
    }

    public function create(): View
    {
        return view('vehicles.create');
    }

    public function store(VehicleRequest $request, AuditService $audit): RedirectResponse
    {
        $vehicle = Vehicle::create($request->normalized());
        $audit->created($vehicle, 'Araç veya makine tanımı oluşturuldu.');

        return redirect()->route('araclar.index')->with('success', 'Araç veya makine eklendi.');
    }

    public function edit(Vehicle $vehicle): View
    {
        return view('vehicles.edit', compact('vehicle'));
    }

    public function update(VehicleRequest $request, Vehicle $vehicle, AuditService $audit): RedirectResponse
    {
        $audit->update($vehicle, $request->normalized(), 'Araç veya makine tanımı güncellendi.');

        return redirect()->route('araclar.index')->with('success', 'Tanım güncellendi.');
    }

    public function destroy(Vehicle $vehicle, AuditService $audit): RedirectResponse
    {
        abort_if($vehicle->fuelEntries()->exists(), 422, 'Yakıt kaydı bulunan araç silinemez; pasif yapın.');
        $audit->delete($vehicle, 'Kullanılmamış araç veya makine tanımı silindi.');

        return back()->with('success', 'Tanım silindi.');
    }
}
