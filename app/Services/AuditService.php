<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditService
{
    private const SENSITIVE_KEYS = [
        'password', 'password_confirmation', 'current_password', 'remember_token',
        'session', 'payload', 'document_contents', 'receipt_contents',
    ];

    public function created(Model $model, ?string $reason = null): void
    {
        $this->write($model, 'created', null, $model->getAttributes(), $reason);
    }

    public function event(Model $model, string $event, ?array $old = null, ?array $new = null, ?string $reason = null): void
    {
        $this->write($model, $event, $old, $new, $reason);
    }

    public function update(Model $model, array $values, string $reason): Model
    {
        return DB::transaction(function () use ($model, $values, $reason): Model {
            $old = $model->getAttributes();
            $model->fill($values);
            $model->save();

            $new = $model->fresh()->getAttributes();
            $documentChanged = ($old['document_path'] ?? null) !== ($new['document_path'] ?? null)
                || ($old['receipt_path'] ?? null) !== ($new['receipt_path'] ?? null);
            $this->write($model, $documentChanged ? 'document_changed' : 'updated', $old, $new, $reason);

            return $model;
        });
    }

    public function delete(Model $model, string $reason): void
    {
        DB::transaction(function () use ($model, $reason): void {
            $old = $model->getAttributes();
            $model->delete();
            $this->write($model, 'deleted', $old, null, $reason);
        });
    }

    private function write(Model $model, string $event, ?array $old, ?array $new, ?string $reason): void
    {
        AuditLog::query()->create([
            'auditable_type' => $model::class,
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'old_values' => $this->sanitize($old),
            'new_values' => $this->sanitize($new),
            'reason' => $reason,
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private function sanitize(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        foreach (self::SENSITIVE_KEYS as $key) {
            unset($values[$key]);
        }

        return $values;
    }
}
