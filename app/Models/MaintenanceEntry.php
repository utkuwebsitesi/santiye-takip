<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'vehicle_id', 'transaction_id', 'maintenance_date', 'maintenance_type',
        'service_provider', 'cost', 'meter_value', 'operating_hours', 'next_maintenance_date',
        'next_meter_value', 'next_operating_hours', 'description', 'document_path', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_date' => 'date',
            'next_maintenance_date' => 'date',
            'cost' => 'decimal:2',
            'meter_value' => 'decimal:1',
            'operating_hours' => 'decimal:1',
            'next_meter_value' => 'decimal:1',
            'next_operating_hours' => 'decimal:1',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getIsDueAttribute(): bool
    {
        return $this->next_maintenance_date?->isPast() === true
            || ($this->next_meter_value !== null && (float) $this->vehicle->fuelEntries()->max('meter_value') >= (float) $this->next_meter_value)
            || ($this->next_operating_hours !== null && (float) $this->vehicle->fuelEntries()->max('operating_hours') >= (float) $this->next_operating_hours);
    }
}
