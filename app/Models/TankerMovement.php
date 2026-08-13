<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TankerMovement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tanker_id', 'type', 'movement_date', 'movement_time', 'liters', 'unit_cost',
        'total_amount', 'balance_after', 'transaction_id', 'fuel_entry_id', 'supplier',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'liters' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'total_amount' => 'decimal:2',
            'balance_after' => 'decimal:3',
        ];
    }

    public function tanker(): BelongsTo
    {
        return $this->belongsTo(Tanker::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function fuelEntry(): BelongsTo
    {
        return $this->belongsTo(FuelEntry::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
