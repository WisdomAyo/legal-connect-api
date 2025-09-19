<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone_number',
        'country_id', 'state_id', 'city_id', 'profile_picture',
        'password', 'email_verification_code', 'email_verification_code_expires_at',
        'last_seen_at', 'last_login_at', 'last_login_ip',
        'failed_login_attempts', 'locked_until', 'is_active',
        'two_factor_enabled', 'two_factor_secret', 'trusted_devices',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verification_code',
        'two_factor_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'email_verification_code_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_login_at' => 'datetime',
            'locked_until' => 'datetime',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'trusted_devices' => 'array',
            'failed_login_attempts' => 'integer',

        ];
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     **/
    public function onboardingSteps()
    {
        return $this->hasMany(OnboardingStep::class);
    }

    public function isLocked(): bool
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function lockAccount(int $minutes = 60): void
    {
        $this->update([
            'locked_until' => now()->addMinutes($minutes),
            'failed_login_attempts' => 0, // Reset counter
        ]);
    }

    public function incrementFailedLogins(): void
    {
        $this->increment('failed_login_attempts');

        // Auto-lock after 5 failed attempts
        if ($this->failed_login_attempts >= 5) {
            $this->lockAccount(30); // Lock for 30 minutes
        }
    }

    public function resetFailedLogins(): void
    {
        $this->update(['failed_login_attempts' => 0]);
    }

    public function isTrustedDevice(string $deviceId): bool
    {
        return in_array($deviceId, $this->trusted_devices ?? []);
    }

    public function addTrustedDevice(string $deviceId): void
    {
        $devices = $this->trusted_devices ?? [];
        if (! in_array($deviceId, $devices)) {
            $devices[] = $deviceId;
            $this->update(['trusted_devices' => $devices]);
        }
    }

    public function sendPasswordResetNotification($token): void
    {
        // 2. Use your custom notification
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Get the full name of the user.
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");

    }

    public function lawyerProfile()
    {
        return $this->hasOne(LawyerProfile::class);
    }

    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');

    }

    public function state()
    {
        return $this->belongsTo(State::class, 'state_id');

    }

    public function city()
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function generateVerificationCode(): string
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'email_verification_code' => Hash::make($code),
            'email_verification_code_expires_at' => now()->addMinutes(15),
        ]);

        return $code;
    }

    public function verifyCode(string $code): bool
    {
        if (! $this->email_verification_code ||
            $this->email_verification_code_expires_at->isPast()) {
            return false;
        }

        return Hash::check($code, $this->email_verification_code);
    }

    public function getOnboardingProgress(): array
    {
        if (! $this->hasRole('lawyer')) {
            return ['progress' => 100, 'status' => 'not_applicable'];
        }

        $steps = $this->onboardingSteps;
        $totalSteps = 4; // personal, professional, documents, availability
        $completedSteps = $steps->where('is_completed', true)->count();

        return [
            'progress' => ($completedSteps / $totalSteps) * 100,
            'completed_steps' => $completedSteps,
            'total_steps' => $totalSteps,
            'current_step' => $this->getCurrentOnboardingStep(),
            'status' => $this->lawyerProfile?->status ?? 'pending_onboarding',
        ];
    }

    public function getCurrentOnboardingStep(): ?string
    {
        $stepOrder = ['personal_info', 'professional_info', 'documents', 'availability'];

        foreach ($stepOrder as $step) {
            $stepRecord = $this->onboardingSteps()?->where('step_name', $step)->first();
            if (! $stepRecord || ! $stepRecord->is_completed) {
                return $step;
            }
        }

        return null; // All steps completed
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVerified($query)
    {
        return $query->whereNotNull('email_verified_at');
    }

    public function scopeLawyers($query)
    {
        return $query->whereHas('roles', function ($q) {
            $q->where('name', 'lawyer');
        });
    }
}
