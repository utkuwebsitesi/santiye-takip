<?php

namespace App\Services;

use App\Models\FuelEntry;
use App\Models\MaintenanceEntry;
use Illuminate\Support\Collection;

class MaintenanceReminderService
{
    public function due(): Collection
    {
        $latestIds = MaintenanceEntry::query()
            ->selectRaw('MAX(id)')
            ->groupBy('vehicle_id', 'maintenance_type');

        $entries = MaintenanceEntry::with('vehicle')
            ->whereIn('id', $latestIds)
            ->where(function ($query): void {
                $query->whereNotNull('next_maintenance_date')
                    ->orWhereNotNull('next_meter_value')
                    ->orWhereNotNull('next_operating_hours');
            })
            ->get();

        if ($entries->isEmpty()) {
            return collect();
        }

        $currentMeters = FuelEntry::query()
            ->whereIn('vehicle_id', $entries->pluck('vehicle_id')->unique())
            ->groupBy('vehicle_id')
            ->selectRaw('vehicle_id, MAX(meter_value) as kilometer, MAX(operating_hours) as operating_hours')
            ->get()
            ->keyBy('vehicle_id');

        return $entries->map(function (MaintenanceEntry $entry) use ($currentMeters) {
            $current = $currentMeters->get($entry->vehicle_id);
            $reasons = collect();

            if ($entry->next_maintenance_date?->lte(today())) {
                $reasons->push('Bakım tarihi: '.$entry->next_maintenance_date->format('d.m.Y'));
            }
            if ($entry->next_meter_value !== null && (float) ($current?->kilometer ?? 0) >= (float) $entry->next_meter_value) {
                $reasons->push('KM sınırı: '.number_format($entry->next_meter_value, 0, ',', '.').' km');
            }
            if ($entry->next_operating_hours !== null && (float) ($current?->operating_hours ?? 0) >= (float) $entry->next_operating_hours) {
                $reasons->push('Saat sınırı: '.number_format($entry->next_operating_hours, 1, ',', '.').' saat');
            }

            $entry->reminder_reasons = $reasons;

            return $entry;
        })->filter(fn (MaintenanceEntry $entry) => $entry->reminder_reasons->isNotEmpty())
            ->sortBy(fn (MaintenanceEntry $entry) => $entry->next_maintenance_date?->timestamp ?? PHP_INT_MAX)
            ->values();
    }
}
