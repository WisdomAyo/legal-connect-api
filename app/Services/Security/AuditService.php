<?php

// app/Services/Security/AuditService.php

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Audit logging service for compliance and security
 *
 * Why: Legal platform needs comprehensive audit trail
 * Required for compliance and security investigations
 */
class AuditService
{
    /**
     * Log an action
     */
    public function log(string $action, ?User $user = null, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $user?->id,
            'action' => $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => json_encode($metadata),
            'created_at' => now(),
        ]);
    }

    /**
     * Log model changes
     */
    public function logModelChange(string $action, $model, array $oldValues = [], array $newValues = []): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'action' => $action,
            'model_type' => get_class($model),
            'model_id' => $model->id,
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode($newValues),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
        ]);
    }

    /**
     * Log failed login attempt
     */
    public function logFailedLogin(string $email, string $ip): void
    {
        $this->log('failed_login', null, [
            'email' => $email,
            'ip' => $ip,
        ]);
    }

    /**
     * Log password reset request
     */
    public function logPasswordResetRequest(string $email, string $ip): void
    {
        $this->log('password_reset_request', null, [
            'email' => $email,
            'ip' => $ip,
        ]);
    }
}
