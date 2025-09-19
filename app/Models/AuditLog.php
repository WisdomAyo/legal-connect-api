<?php

// app/Models/AuditLog.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditLog Model - Tracks all sensitive actions for compliance
 *
 * Why: Legal platforms need comprehensive audit trails for:
 * - Security investigations
 * - Compliance requirements
 * - User activity monitoring
 * - Debugging production issues
 */
class AuditLog extends Model
{
    // Disable timestamps since we only need created_at
    public $timestamps = false;

    // Only created_at is automatically managed
    protected $dates = ['created_at'];

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Boot method to set created_at
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->created_at ?? now();
        });
    }

    /**
     * RELATIONSHIPS
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the model that was audited
     */
    public function auditable()
    {
        if ($this->model_type && $this->model_id) {
            return $this->model_type::find($this->model_id);
        }

        return null;
    }

    /**
     * SCOPES
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * HELPER METHODS
     */
    public function getChangedAttributes(): array
    {
        if (! $this->old_values || ! $this->new_values) {
            return [];
        }

        $changed = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changed[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changed;
    }

    /**
     * Check if a specific attribute was changed
     */
    public function wasAttributeChanged(string $attribute): bool
    {
        $changedAttributes = $this->getChangedAttributes();

        return array_key_exists($attribute, $changedAttributes);
    }

    /**
     * Get human-readable action description
     */
    public function getActionDescription(): string
    {
        return match ($this->action) {
            'user_registered' => 'User registered',
            'user_login' => 'User logged in',
            'user_logout' => 'User logged out',
            'email_verified' => 'Email verified',
            'password_reset' => 'Password reset',
            'profile_updated' => 'Profile updated',
            'onboarding_step_completed' => 'Onboarding step completed',
            'onboarding_submitted' => 'Onboarding submitted for review',
            'failed_login' => 'Failed login attempt',
            default => ucfirst(str_replace('_', ' ', $this->action))
        };
    }
}
