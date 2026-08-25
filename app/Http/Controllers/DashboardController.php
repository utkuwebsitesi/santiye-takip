<?php

namespace App\Http\Controllers;

use App\Models\FuelEntry;
use App\Models\Tanker;
use App\Models\Transaction;
use App\Models\Vehicle;
use App\Services\MaintenanceReminderService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $user = request()->user();
        $canTransactions = $user->hasPermission('transactions.view');
        $canFuel = $user->hasPermission('fuel.view');
        $canTankers = $user->hasPermission('tankers.view');
        $canVehicles = $user->hasPermission('vehicles.view');
        $canMaintenance = $user->hasPermission('maintenance.view');

        $today = now(config('app.timezone'))->startOfDay();
        $todayDate = $today->toDateString();
        $yesterdayDate = $today->copy()->subDay()->toDateString();
        $start = $today->copy()->startOfMonth()->toDateString();
        $chartStart = $today->copy()->subDays(6);
        $chartStartDate = $chartStart->toDateString();
        $income = $expense = $fuel = $carryBalance = $monthlyNet = $todayExpense = 0.0;
        $monthlyFuelLiters = $totalFuelLiters = $activeVehicleCount = 0.0;
        $tankers = collect(); $recentFuel = collect(); $recentTransactions = collect(); $dueMaintenance = collect();
        $highestTankerStock = 1.0; $recentFuelPeak = 1.0;
        $cashSeries = array_fill(0, 7, 0.0); $fuelSeries = array_fill(0, 7, 0.0); $dashboardMetrics = [];

        if ($canTransactions) {
            $income = (float) Transaction::query()->where('affects_cash', true)->where('type', 'income')->whereDate('occurred_on', '>=', $start)->sum('amount');
            $expense = (float) Transaction::query()->where('affects_cash', true)->where('type', 'expense')->whereDate('occurred_on', '>=', $start)->sum('amount');
            $carryIncome = (float) Transaction::query()->where('affects_cash', true)->where('type', 'income')->whereDate('occurred_on', '<', $start)->sum('amount');
            $carryExpense = (float) Transaction::query()->where('affects_cash', true)->where('type', 'expense')->whereDate('occurred_on', '<', $start)->sum('amount');
            $carryBalance = $carryIncome - $carryExpense; $monthlyNet = $income - $expense;
            $todayExpense = (float) Transaction::query()->where('affects_cash', true)->where('type', 'expense')->whereDate('occurred_on', $todayDate)->sum('amount');
            $dailyCashNet = Transaction::query()->where('affects_cash', true)->whereBetween('occurred_on', [$chartStartDate, $todayDate])
                ->selectRaw("DATE(occurred_on) as day, SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as total")->groupBy('day')->pluck('total', 'day')->all();
            $cashBeforeChart = (float) Transaction::query()->where('affects_cash', true)->whereDate('occurred_on', '<', $chartStartDate)
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as total")->value('total');
            $runningCash = $cashBeforeChart; $cashSeries = [];
            foreach ($this->sevenDayDates($chartStart) as $date) { $runningCash += (float) ($dailyCashNet[$date] ?? 0); $cashSeries[] = $runningCash; }
            $dailyExpense = Transaction::query()->where('affects_cash', true)->where('type', 'expense')->whereBetween('occurred_on', [$chartStartDate, $todayDate])
                ->selectRaw('DATE(occurred_on) as day, SUM(amount) as total')->groupBy('day')->pluck('total', 'day')->all();
            $todayIncome = (float) Transaction::query()->where('affects_cash', true)->where('type', 'income')->whereDate('occurred_on', $todayDate)->sum('amount');
            $dashboardMetrics['cash'] = ['icon' => '₺', 'title' => 'Toplam kasa bakiyesi', 'value' => $carryBalance + $monthlyNet, 'format' => 'currency', 'subtitle' => 'Güncel bakiye', 'series' => $cashSeries, 'trend' => $this->trend($todayIncome - $todayExpense, 'bakiye', '₺', 2, true)];
            $dashboardMetrics['expense'] = ['icon' => '₺', 'title' => 'Bugünkü harcama', 'value' => $todayExpense, 'format' => 'currency', 'subtitle' => 'Bugün kasadan çıkan tutar', 'series' => $this->seriesFromBuckets($dailyExpense, $chartStart), 'trend' => $this->trend((float) $todayExpense - (float) ($dailyExpense[$yesterdayDate] ?? 0), 'harcama', '₺', 2, false)];
            $recentTransactions = Transaction::with('creator')->where('affects_cash', true)->latest('occurred_on')->latest('id')->limit(25)->get();
        }

        if ($canFuel) {
            $fuel = (float) FuelEntry::query()->whereDate('fuel_date', '>=', $start)->sum('total_amount');
            $monthlyFuelLiters = (float) FuelEntry::query()->whereDate('fuel_date', '>=', $start)->sum('liters');
            $totalFuelLiters = (float) FuelEntry::query()->sum('liters');
            $recentFuel = FuelEntry::with(['vehicle', 'tanker', 'creator'])->latest('fuel_date')->latest('id')->limit(8)->get();
            $recentFuelPeak = max(1, (float) $recentFuel->take(4)->max('liters'));
            $dailyFuel = FuelEntry::query()->whereDate('fuel_date', '>=', $chartStartDate)->whereDate('fuel_date', '<=', $todayDate)
                ->selectRaw('DATE(fuel_date) as day, SUM(liters) as total')->groupBy('day')->pluck('total', 'day')->all();
            $fuelBeforeChart = (float) FuelEntry::query()->whereDate('fuel_date', '<', $chartStartDate)->sum('liters');
            $fuelSeries = []; $runningFuel = $fuelBeforeChart;
            foreach ($this->sevenDayDates($chartStart) as $date) { $runningFuel += (float) ($dailyFuel[$date] ?? 0); $fuelSeries[] = $runningFuel; }
            $dashboardMetrics['fuel'] = ['icon' => 'L', 'title' => 'Toplam yakıt tüketimi', 'value' => $totalFuelLiters, 'format' => 'liters', 'subtitle' => 'Tankerlerden toplam dağıtım', 'series' => $fuelSeries, 'trend' => $this->trend((float) ($dailyFuel[$todayDate] ?? 0) - (float) ($dailyFuel[$yesterdayDate] ?? 0), 'yakıt dağıtımı', 'L', 0, false)];
        }

        if ($canVehicles) {
            $activeVehicleCount = (int) Vehicle::query()->where('is_active', true)->count();
            $dashboardMetrics['fleet'] = ['icon' => '#', 'title' => 'Aktif araç & makine', 'value' => $activeVehicleCount, 'format' => 'count', 'subtitle' => 'Sistemde takipteki filo', 'series' => array_fill(0, 7, (float) $activeVehicleCount), 'trend' => $this->trend(0, 'aktif araç sayısı', 'araç', 0)];
        }
        if ($canTankers) { $tankers = Tanker::query()->where('is_active', true)->orderBy('name')->get(); $highestTankerStock = max(1, (float) $tankers->max('stock_liters')); }
        if ($canMaintenance) { $dueMaintenance = app(MaintenanceReminderService::class)->due()->take(4); }

        return view('dashboard', ['income' => $income, 'expense' => $expense, 'fuel' => $fuel, 'monthlyNet' => $monthlyNet, 'carryBalance' => $carryBalance, 'balance' => $carryBalance + $monthlyNet, 'todayExpense' => $todayExpense, 'monthlyFuelLiters' => $monthlyFuelLiters, 'totalFuelLiters' => $totalFuelLiters, 'activeVehicleCount' => $activeVehicleCount, 'dashboardMetrics' => $dashboardMetrics, 'tankers' => $tankers, 'totalTankerStock' => $tankers->sum('stock_liters'), 'highestTankerStock' => $highestTankerStock, 'dueMaintenance' => $dueMaintenance, 'recentTransactions' => $recentTransactions, 'recentFuel' => $recentFuel, 'recentFuelPeak' => $recentFuelPeak]);
    }

    private function sevenDayDates(Carbon $start): array { return collect(range(0, 6))->map(fn (int $offset) => $start->copy()->addDays($offset)->toDateString())->all(); }
    private function seriesFromBuckets(array $buckets, Carbon $start): array { return collect($this->sevenDayDates($start))->map(fn (string $date) => (float) ($buckets[$date] ?? 0))->all(); }

    private function trend(float $change, string $subject, string $unit, int $precision, ?bool $higherIsPositive = null): array
    {
        $direction = abs($change) < 0.0001 ? 'flat' : ($change > 0 ? 'up' : 'down'); $tone = 'neutral';
        if ($higherIsPositive !== null && $direction !== 'flat') { $tone = (($direction === 'up') === $higherIsPositive) ? 'positive' : 'negative'; }
        $formatted = number_format(abs($change), $precision, ',', '.').' '.$unit;
        return ['direction' => $direction, 'tone' => $tone, 'arrow' => match ($direction) { 'up' => '↑', 'down' => '↓', default => '→' }, 'text' => $direction === 'flat' ? 'Dünle aynı seviyede' : 'Düne göre '.$subject.' '.($direction === 'up' ? 'arttı' : 'azaldı').': '.$formatted];
    }
}
