<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MobileAccessToken extends Model
{
    protected $fillable = [
        'user_id', 'token_hash', 'device_name', 'last_used_at', 'expires_at', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array{token: string, model: self}
     */
    public static function issue(User $user, ?string $deviceName = null): array
    {
        $plainToken = 'st_'.Str::random(64);
        $model = self::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $plainToken),
            'device_name' => $deviceName ? Str::limit(trim($deviceName), 120, '') : null,
            'last_used_at' => now(),
            'expires_at' => now()->addDays(max(1, config('mobile.token_max_days'))),
        ]);

        return ['token' => $plainToken, 'model' => $model];
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null || $this->expires_at->isPast() || ! $this->user?->is_active) {
            return false;
        }

        $idleLimit = max(1, config('mobile.token_idle_minutes'));

        return $this->last_used_at?->greaterThanOrEqualTo(now()->subMinutes($idleLimit)) ?? false;
    }

    public function markUsed(): void
    {
        if ($this->last_used_at === null || $this->last_used_at->lt(now()->subMinute())) {
            $this->forceFill(['last_used_at' => now()])->save();
        }
    }
}
