<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'category', 'description', 'amount', 'occurred_on',
        'affects_cash', 'document_path', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'affects_cash' => 'boolean', 'occurred_on' => 'date'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fuelEntry(): HasOne
    {
        return $this->hasOne(FuelEntry::class);
    }

    public function maintenanceEntry(): HasOne
    {
        return $this->hasOne(MaintenanceEntry::class);
    }
}
