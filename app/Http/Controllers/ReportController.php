<?php

namespace App\Http\Controllers;

use App\Models\FuelEntry;
use App\Models\MaintenanceEntry;
use App\Models\Tanker;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\FuelService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __invoke(Request $request, FuelService $fuelService): View
    {
        $transactions = Transaction::with('creator')
            ->where('affects_cash', true)
            ->when($request->user()->isAdmin() && $request->boolean('with_deleted'), fn ($q) => $q->withTrashed())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('occurred_on', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('occurred_on', '<=', $request->date('to')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->filled('created_by'), fn ($q) => $q->where('created_by', $request->integer('created_by')))
            ->latest('occurred_on')->paginate(25, ['*'], 'transactions_page')->withQueryString();

        $fuelQuery = FuelEntry::with(['vehicle', 'tanker', 'creator'])
            ->when($request->user()->isAdmin() && $request->boolean('with_deleted'), fn ($q) => $q->withTrashed())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('fuel_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('fuel_date', '<=', $request->date('to')))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')));

        $fuel = (clone $fuelQuery)->latest('fuel_date')->paginate(25, ['*'], 'fuel_page')->withQueryString();
        $summaryRows = (clone $fuelQuery)->get();
        $fuelTotals = $summaryRows->groupBy(fn ($row) => $row->fuel_date->toDateString())
            ->map(fn ($rows, $date) => (object) ['period' => $date, 'liters' => $rows->sum('liters'), 'amount' => $rows->sum('total_amount')])
            ->sortKeysDesc()->take(31);
        $monthlyFuelTotals = $summaryRows->groupBy(fn ($row) => $row->fuel_date->format('Y-m'))
            ->map(fn ($rows, $month) => (object) ['period' => $month, 'liters' => $rows->sum('liters'), 'amount' => $rows->sum('total_amount')])
            ->sortKeysDesc()->take(12);
        $efficiency = $summaryRows->groupBy('vehicle_id')->map(function ($rows) use ($fuelService) {
            $rates = $fuelService->consumptionRates($rows);

            return (object) [
                'vehicle' => $rows->first()->vehicle,
                ...$rates,
            ];
        });

        $maintenance = MaintenanceEntry::with(['vehicle', 'creator'])
            ->when($request->user()->isAdmin() && $request->boolean('with_deleted'), fn ($q) => $q->withTrashed())
            ->when($request->filled('from'), fn ($q) => $q->whereDate('maintenance_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('maintenance_date', '<=', $request->date('to')))
            ->when($request->filled('vehicle_id'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle_id')))
            ->latest('maintenance_date')->latest('id')->paginate(25, ['*'], 'maintenance_page')->withQueryString();

        return view('reports.index', [
            'transactions' => $transactions,
            'fuel' => $fuel,
            'fuelTotals' => $fuelTotals,
            'monthlyFuelTotals' => $monthlyFuelTotals,
            'efficiency' => $efficiency,
            'maintenance' => $maintenance,
            'tankers' => Tanker::query()->where('is_active', true)->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
            'vehicles' => Vehicle::orderBy('name')->get(),
            'categories' => Transaction::query()->where('affects_cash', true)->distinct()->orderBy('category')->pluck('category'),
        ]);
    }
}
