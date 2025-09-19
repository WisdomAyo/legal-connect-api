<?php

// app/Models/LawyerProfile.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LawyerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bio',
        'nba_enrollment_number',
        'year_of_call',
        'law_school',
        'graduation_year',
        'office_address',
        'status',
        'hourly_rate',
        'cv_path',
        'bar_certificate_path',
        'consultation_fee',
        'availability',
        'verified_at',
        'submitted_for_review_at',
        'rejection_reason',
        'average_rating',
        'total_reviews',
    ];

    protected $casts = [
        'availability' => 'array',
        'verified_at' => 'datetime',
        'submitted_for_review_at' => 'datetime',
        'hourly_rate' => 'decimal:2',
        'consultation_fee' => 'decimal:2',
        'average_rating' => 'decimal:2',
    ];

    /**
     * Status constants
     */
    const STATUS_PENDING_ONBOARDING = 'pending_onboarding';

    const STATUS_IN_PROGRESS = 'in_progress';

    const STATUS_PENDING_REVIEW = 'pending_review';

    const STATUS_VERIFIED = 'verified';

    const STATUS_REJECTED = 'rejected';

    const STATUS_SUSPENDED = 'suspended';

    /**
     * RELATIONSHIPS
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function practiceAreas(): BelongsToMany
    {
        return $this->belongsToMany(PracticeArea::class, 'lawyer_practice_area');
    }

    public function specializations(): BelongsToMany
    {
        return $this->belongsToMany(Specialization::class, 'lawyer_specialization');
    }

    public function languages(): BelongsToMany
    {
        return $this->belongsToMany(Language::class, 'lawyer_language');
    }

    /**
     * ACCESSORS
     */
    public function getFullNameAttribute(): string
    {
        return $this->user->full_name;
    }

    public function getIsVerifiedAttribute(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function getCanEditProfileAttribute(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING_ONBOARDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_REJECTED,
        ]);
    }

    /**
     * SCOPES
     */
    public function scopeVerified($query)
    {
        return $query->where('status', self::STATUS_VERIFIED);
    }

    public function scopePendingReview($query)
    {
        return $query->where('status', self::STATUS_PENDING_REVIEW);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_VERIFIED,
            self::STATUS_PENDING_REVIEW,
        ]);
    }

    /**
     * HELPER METHODS
     */
    public function submitForReview(): void
    {
        $this->update([
            'status' => self::STATUS_PENDING_REVIEW,
            'submitted_for_review_at' => now(),
        ]);
    }

    public function approve(): void
    {
        $this->update([
            'status' => self::STATUS_VERIFIED,
            'verified_at' => now(),
            'rejection_reason' => null,
        ]);
    }

    public function reject(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'verified_at' => null,
        ]);
    }

    public function suspend(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_SUSPENDED,
            'rejection_reason' => $reason,
        ]);
    }
}
