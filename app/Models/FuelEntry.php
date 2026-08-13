<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FuelEntry extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transaction_id', 'vehicle_id', 'tanker_id', 'fuel_date', 'fuel_time', 'liters', 'unit_price', 'total_amount',
        'meter_value', 'operating_hours', 'station', 'receipt_path', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'fuel_date' => 'date',
            'liters' => 'decimal:3',
            'unit_price' => 'decimal:3',
            'total_amount' => 'decimal:2',
            'meter_value' => 'decimal:1',
            'operating_hours' => 'decimal:1',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function tanker(): BelongsTo
    {
        return $this->belongsTo(Tanker::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
