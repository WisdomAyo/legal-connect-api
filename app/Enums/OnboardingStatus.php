<?php

// app/Enums/OnboardingStatus.php

namespace App\Enums;

enum OnboardingStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Suspended = 'suspended';

    public function canEdit(): bool
    {
        return in_array($this, [self::NotStarted, self::InProgress, self::Rejected]);
    }

    public function isComplete(): bool
    {
        return in_array($this, [self::Approved, self::UnderReview]);
    }
}
