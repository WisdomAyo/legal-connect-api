<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;





class LawyerProfile extends Model
{
    //

    use HasFactory;

    protected $fillable = [
        'user_id',
        'bio',
        'nba_enrollment_number',
        'year_of_call',
        'office_address',
        'status',
        'hourly_rate',
        'cv_path',
        'consultation_fee',
        'availability',
        'verified_at',
        'specializations',
        'profile_picture'
    ];

    protected $casts = [
        'availability' => 'array',
        'verified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function praticeAreas()
    {
        return $this->belongsToMany(PracticeArea::class, 'lawyer_practice_area');
    }

    public function specializations()
    {
        return $this->belongsToMany(Specialization::class, 'lawyer_specialization');
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class, 'lawyer_language');
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->user->first_name} {$this->user->last_name}";
    }
}
