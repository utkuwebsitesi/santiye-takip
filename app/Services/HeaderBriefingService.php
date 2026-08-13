<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class HeaderBriefingService
{
    public function get(): array
    {
        if (app()->environment('testing')) {
            return ['weather' => [], 'rates' => [], 'weather_updated_at' => null, 'rates_updated_at' => null];
        }

        $weather = $this->weather();
        $rates = $this->exchangeRates();

        return [
            'weather' => $weather['items'],
            'rates' => $rates['items'],
            'weather_updated_at' => $weather['updated_at'],
            'rates_updated_at' => $rates['updated_at'],
        ];
    }

    private function weather(): array
    {
        try {
            return Cache::remember('header.weather.ankara.v2', now()->addMinutes(30), function (): array {
                $daily = Http::timeout(5)->acceptJson()->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => 39.9334,
                    'longitude' => 32.8597,
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
                    'timezone' => 'Europe/Istanbul',
                    'forecast_days' => 5,
                ])->throw()->json('daily', []);

                $items = collect($daily['time'] ?? [])->map(function ($date, $index) use ($daily): array {
                    $code = (int) ($daily['weather_code'][$index] ?? 0);
                    [$icon, $condition] = $this->weatherCondition($code);

                    return [
                        'day' => Carbon::parse($date)->translatedFormat('D'),
                        'date' => Carbon::parse($date)->format('d.m'),
                        'icon' => $icon,
                        'condition' => $condition,
                        'max' => (int) round($daily['temperature_2m_max'][$index] ?? 0),
                        'min' => (int) round($daily['temperature_2m_min'][$index] ?? 0),
                    ];
                })->take(5)->all();

                return ['items' => $items, 'updated_at' => now()->format('H:i')];
            });
        } catch (Throwable) {
            return ['items' => [], 'updated_at' => null];
        }
    }

    private function exchangeRates(): array
    {
        try {
            return Cache::remember('header.exchange-rates.tcmb.v2', now()->addMinutes(45), function (): array {
                $xml = Http::timeout(5)->get('https://www.tcmb.gov.tr/kurlar/today.xml')->throw()->body();
                $document = simplexml_load_string($xml);

                if ($document === false) {
                    return ['items' => [], 'updated_at' => null];
                }

                $rates = [];
                foreach (['USD' => '$', 'EUR' => '€'] as $code => $icon) {
                    $currency = $document->xpath("//Currency[@CurrencyCode='{$code}']")[0] ?? null;
                    $value = $currency ? (float) str_replace(',', '.', (string) $currency->ForexSelling) : 0;
                    if ($value > 0) {
                        $rates[] = ['code' => $code, 'icon' => $icon, 'value' => number_format($value, 4, ',', '.')];
                    }
                }

                return ['items' => $rates, 'updated_at' => now()->format('H:i')];
            });
        } catch (Throwable) {
            return ['items' => [], 'updated_at' => null];
        }
    }

    private function weatherCondition(int $code): array
    {
        return match (true) {
            $code === 0 => ['☀️', 'Açık'],
            in_array($code, [1, 2], true) => ['🌤️', 'Az bulutlu'],
            $code === 3 => ['☁️', 'Bulutlu'],
            in_array($code, [45, 48], true) => ['🌫️', 'Sisli'],
            in_array($code, [51, 53, 55, 56, 57], true) => ['🌦️', 'Çisenti'],
            in_array($code, [61, 63, 65, 66, 67, 80, 81, 82], true) => ['🌧️', 'Yağmurlu'],
            in_array($code, [71, 73, 75, 77, 85, 86], true) => ['🌨️', 'Karlı'],
            in_array($code, [95, 96, 99], true) => ['⛈️', 'Fırtınalı'],
            default => ['🌤️', 'Değişken'],
        };
    }
}
