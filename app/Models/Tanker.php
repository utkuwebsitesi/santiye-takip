<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tanker extends Model
{
    protected $fillable = ['name', 'stock_liters', 'average_unit_cost', 'is_active'];

    protected function casts(): array
    {
        return [
            'stock_liters' => 'decimal:3',
            'average_unit_cost' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(TankerMovement::class);
    }

    public function fuelEntries(): HasMany
    {
        return $this->hasMany(FuelEntry::class);
    }
}
