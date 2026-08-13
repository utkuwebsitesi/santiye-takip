<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = ['type', 'name', 'plate', 'code', 'tracking_unit', 'tracks_meters', 'is_active'];

    protected function casts(): array
    {
        return ['tracks_meters' => 'boolean', 'is_active' => 'boolean'];
    }

    public function fuelEntries(): HasMany
    {
        return $this->hasMany(FuelEntry::class);
    }

    public function maintenanceEntries(): HasMany
    {
        return $this->hasMany(MaintenanceEntry::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->type === 'vehicle' ? ($this->plate.' — '.$this->name) : ($this->code.' — '.$this->name);
    }
}
