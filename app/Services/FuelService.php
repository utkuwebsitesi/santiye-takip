<?php

namespace App\Services;

use App\Models\FuelEntry;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FuelService
{
    public function calculateTotal(string $liters, string $unitPrice): string
    {
        $scale = 1000000;
        $litersScaled = (int) round((float) $liters * 1000);
        $priceScaled = (int) round((float) $unitPrice * 1000);

        return number_format(($litersScaled * $priceScaled) / $scale, 2, '.', '');
    }

    public function consumptionRates(Collection $entries): array
    {
        $entries = $entries->sortBy(fn (FuelEntry $entry) => $entry->fuel_date->toDateString().' '.($entry->fuel_time ?? '00:00'));

        $calculate = function (string $field, float $factor) use ($entries): ?float {
            $meterRows = $entries->whereNotNull($field)->values();
            if ($meterRows->count() < 2) {
                return null;
            }
            $usage = (float) $meterRows->last()->{$field} - (float) $meterRows->first()->{$field};
            $liters = (float) $meterRows->skip(1)->sum('liters');

            return $usage > 0 ? ($liters / $usage) * $factor : null;
        };

        return [
            'km_rate' => $calculate('meter_value', 100),
            'hour_rate' => $calculate('operating_hours', 1),
        ];
    }

    public function validateMeterSequence(array $data, User $user, ?FuelEntry $current = null, ?string $overrideReason = null): void
    {
        $moment = $data['fuel_date'].' '.($data['fuel_time'] ?? '00:00');
        $entries = FuelEntry::where('vehicle_id', $data['vehicle_id'])
            ->when($current, fn ($query) => $query->whereKeyNot($current->id))
            ->get()
            ->sortBy(fn (FuelEntry $entry) => $entry->fuel_date->toDateString().' '.($entry->fuel_time ?? '00:00'));

        $previous = $entries->last(fn (FuelEntry $entry) => ($entry->fuel_date->toDateString().' '.($entry->fuel_time ?? '00:00')) <= $moment);
        $next = $entries->first(fn (FuelEntry $entry) => ($entry->fuel_date->toDateString().' '.($entry->fuel_time ?? '00:00')) > $moment);

        foreach (['meter_value' => 'Kilometre', 'operating_hours' => 'Çalışma saati'] as $field => $label) {
            if (($data[$field] ?? null) === null || $data[$field] === '') {
                continue;
            }
            $invalid = ($previous?->{$field} !== null && (float) $data[$field] < (float) $previous->{$field})
                || ($next?->{$field} !== null && (float) $data[$field] > (float) $next->{$field});

            if ($invalid && (! $user->isAdmin() || ! $overrideReason)) {
                throw ValidationException::withMessages([
                    $field => "{$label} değeri kronolojik kayıtlarla uyumlu değil.",
                    'meter_override_reason' => $user->isAdmin() ? 'Sayaç istisnası için yönetici gerekçesi zorunludur.' : null,
                ]);
            }
        }
    }
}
