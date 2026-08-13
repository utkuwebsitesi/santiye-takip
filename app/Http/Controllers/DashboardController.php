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
        $today = now(config('app.timezone'))->startOfDay();
        $todayDate = $today->toDateString();
        $yesterdayDate = $today->copy()->subDay()->toDateString();
        $start = $today->copy()->startOfMonth()->toDateString();
        $chartStart = $today->copy()->subDays(6);
        $chartStartDate = $chartStart->toDateString();
        $income = Transaction::query()->where('affects_cash', true)->where('type', 'income')->whereDate('occurred_on', '>=', $start)->sum('amount');
        $expense = Transaction::query()->where('affects_cash', true)->where('type', 'expense')->whereDate('occurred_on', '>=', $start)->sum('amount');
        $fuel = FuelEntry::query()->whereDate('fuel_date', '>=', $start)->sum('total_amount');
        $carryIncome = Transaction::query()->where('affects_cash', true)->where('type', 'income')->whereDate('occurred_on', '<', $start)->sum('amount');
        $carryExpense = Transaction::query()->where('affects_cash', true)->where('type', 'expense')->whereDate('occurred_on', '<', $start)->sum('amount');
        $carryBalance = $carryIncome - $carryExpense;
        $monthlyNet = $income - $expense;
        $todayExpense = Transaction::query()
            ->where('affects_cash', true)
            ->where('type', 'expense')
            ->whereDate('occurred_on', $todayDate)
            ->sum('amount');
        $monthlyFuelLiters = FuelEntry::query()->whereDate('fuel_date', '>=', $start)->sum('liters');
        $totalFuelLiters = FuelEntry::query()->sum('liters');
        $tankers = Tanker::query()->where('is_active', true)->orderBy('name')->get();
        $highestTankerStock = max(1, (float) $tankers->max('stock_liters'));
        $recentFuel = FuelEntry::with(['vehicle', 'tanker', 'creator'])->latest('fuel_date')->limit(8)->get();
        $recentFuelPeak = max(1, (float) $recentFuel->take(4)->max('liters'));
        $dailyCashNet = Transaction::query()
            ->where('affects_cash', true)
            ->whereBetween('occurred_on', [$chartStartDate, $todayDate])
            ->selectRaw("DATE(occurred_on) as day, SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as total")
            ->groupBy('day')->pluck('total', 'day')->all();
        $cashBeforeChart = (float) Transaction::query()
            ->where('affects_cash', true)
            ->whereDate('occurred_on', '<', $chartStartDate)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 0) as total")
            ->value('total');
        $cashSeries = [];
        $runningCash = $cashBeforeChart;
        foreach ($this->sevenDayDates($chartStart) as $date) {
            $runningCash += (float) ($dailyCashNet[$date] ?? 0);
            $cashSeries[] = $runningCash;
        }
        $dailyExpense = Transaction::query()
            ->where('affects_cash', true)->where('type', 'expense')
            ->whereBetween('occurred_on', [$chartStartDate, $todayDate])
            ->selectRaw('DATE(occurred_on) as day, SUM(amount) as total')
            ->groupBy('day')->pluck('total', 'day')->all();
        $dailyFuel = FuelEntry::query()
            ->whereDate('fuel_date', '>=', $chartStartDate)
            ->whereDate('fuel_date', '<=', $todayDate)
            ->selectRaw('DATE(fuel_date) as day, SUM(liters) as total')
            ->groupBy('day')->pluck('total', 'day')->all();
        $fuelBeforeChart = (float) FuelEntry::query()
            ->whereDate('fuel_date', '<', $chartStartDate)
            ->sum('liters');
        $fuelSeries = [];
        $runningFuel = $fuelBeforeChart;
        foreach ($this->sevenDayDates($chartStart) as $date) {
            $runningFuel += (float) ($dailyFuel[$date] ?? 0);
            $fuelSeries[] = $runningFuel;
        }
        $todayIncome = (float) Transaction::query()->where('affects_cash', true)->where('type', 'income')
            ->whereDate('occurred_on', $todayDate)->sum('amount');
        $cashDailyChange = $todayIncome - (float) $todayExpense;
        $activeVehicleCount = Vehicle::query()->where('is_active', true)->count();
        $dashboardMetrics = [
            'cash' => [
                'icon' => '₺', 'title' => 'Toplam kasa bakiyesi', 'value' => $carryBalance + $monthlyNet,
                'format' => 'currency', 'subtitle' => 'Güncel bakiye', 'series' => $cashSeries,
                'trend' => $this->trend($cashDailyChange, 'bakiye', '₺', 2, true),
            ],
            'expense' => [
                'icon' => '₺', 'title' => 'Bugünkü harcama', 'value' => $todayExpense,
                'format' => 'currency', 'subtitle' => 'Bugün kasadan çıkan tutar',
                'series' => $this->seriesFromBuckets($dailyExpense, $chartStart),
                'trend' => $this->trend((float) $todayExpense - (float) ($dailyExpense[$yesterdayDate] ?? 0), 'harcama', '₺', 2, false),
            ],
            'fuel' => [
                'icon' => 'L', 'title' => 'Toplam yakıt tüketimi', 'value' => $totalFuelLiters,
                'format' => 'liters', 'subtitle' => 'Tankerlerden toplam dağıtım',
                'series' => $fuelSeries,
                'trend' => $this->trend((float) ($dailyFuel[$todayDate] ?? 0) - (float) ($dailyFuel[$yesterdayDate] ?? 0), 'yakıt dağıtımı', 'L', 0, false),
            ],
            'fleet' => [
                'icon' => '#', 'title' => 'Aktif araç & makine', 'value' => $activeVehicleCount,
                'format' => 'count', 'subtitle' => 'Sistemde takipteki filo',
                'series' => array_fill(0, 7, (float) $activeVehicleCount),
                'trend' => $this->trend(0, 'aktif araç sayısı', 'araç', 0),
            ],
        ];

        return view('dashboard', [
            'income' => $income,
            'expense' => $expense,
            'fuel' => $fuel,
            'monthlyNet' => $monthlyNet,
            'carryBalance' => $carryBalance,
            'balance' => $carryBalance + $monthlyNet,
            'todayExpense' => $todayExpense,
            'monthlyFuelLiters' => $monthlyFuelLiters,
            'totalFuelLiters' => $totalFuelLiters,
            'activeVehicleCount' => $activeVehicleCount,
            'dashboardMetrics' => $dashboardMetrics,
            'tankers' => $tankers,
            'totalTankerStock' => $tankers->sum('stock_liters'),
            'highestTankerStock' => $highestTankerStock,
            'dueMaintenance' => app(MaintenanceReminderService::class)->due()->take(4),
            'recentTransactions' => Transaction::with('creator')->where('affects_cash', true)->latest('occurred_on')->limit(8)->get(),
            'recentFuel' => $recentFuel,
            'recentFuelPeak' => $recentFuelPeak,
        ]);
    }

    private function sevenDayDates(Carbon $start): array
    {
        return collect(range(0, 6))
            ->map(fn (int $offset) => $start->copy()->addDays($offset)->toDateString())
            ->all();
    }

    private function seriesFromBuckets(array $buckets, Carbon $start): array
    {
        return collect($this->sevenDayDates($start))
            ->map(fn (string $date) => (float) ($buckets[$date] ?? 0))
            ->all();
    }

    private function trend(float $change, string $subject, string $unit, int $precision, ?bool $higherIsPositive = null): array
    {
        $direction = abs($change) < 0.0001 ? 'flat' : ($change > 0 ? 'up' : 'down');
        $tone = 'neutral';
        if ($higherIsPositive !== null && $direction !== 'flat') {
            $tone = (($direction === 'up') === $higherIsPositive) ? 'positive' : 'negative';
        }

        $formatted = number_format(abs($change), $precision, ',', '.').' '.$unit;

        return [
            'direction' => $direction,
            'tone' => $tone,
            'arrow' => match ($direction) {
                'up' => '↑', 'down' => '↓', default => '→'
            },
            'text' => $direction === 'flat'
                ? 'Dünle aynı seviyede'
                : 'Düne göre '.$subject.' '.($direction === 'up' ? 'arttı' : 'azaldı').': '.$formatted,
        ];
    }
}
