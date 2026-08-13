<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NavigationItem extends Model
{
    protected $fillable = ['key', 'label', 'sort_order', 'is_enabled', 'minimum_role'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }
}
