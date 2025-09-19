<?php

namespace App\Repositories;

use App\Models\LawyerProfile;
use App\Models\User;

class LawyerRepository
{
    public function createInitialProfile(User $user, array $data): LawyerProfile
    {
        return $user->lawyerProfile()->create([
            'status' => 'pending_onboarding',
        ]);
    }

    public function updateOnboardingData(User $user, array $updates): LawyerProfile
    {
        $profile = $user->lawyerProfile;

        // The fillable property on the LawyerProfile model will protect from mass assignment
        $profile->update($updates);

        return $profile;
    }

    public function submitForReview(User $user): LawyerProfile
    {
        $profile = $user->lawyerProfile;
        $profile->status = 'pending_review';
        $profile->save();

        // You could dispatch an event here for admins
        // event(new LawyerProfileSubmittedForReview($profile));

        return $profile;
    }

    public function updateProfile(User $user, array $updates): LawyerProfile
    {
        $profile = $user->lawyerProfile;

        $profile->update([
            'bio' => $updates['bio'] ?? $profile->bio,
            'office_address' => $updates['office_address'] ?? $profile->office_address,
            'availability' => $updates['availability'] ?? $profile->availability,
            'consultation_fee' => $updates['consultation_fee'] ?? $profile->consultation_fee,
        ]);

        return $profile;
    }
}
