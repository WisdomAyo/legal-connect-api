<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class UserRepository
{
    public function createUser(array $data): User
    {

        $user = User::create($data);

        return $user->load('city.state.country', 'lawyerProfile');
    }

    public function findByEmail(string $email): ?User
    {
        return User::with('city.state.country', 'lawyerProfile')
            ->where('email', $email)
            ->first();
    }

    public function findOrCreateFromSocial(SocialiteUser $socialUser): User
    {
        $user = User::updateOrCreate(
            ['email' => $socialUser->getEmail()],
            [
                'first_name' => explode(' ', $socialUser->getName())[0] ?? $socialUser->getNickname(),
                'last_name' => explode(' ', $socialUser->getName())[1] ?? $socialUser->getNickname(),
                'profile_picture' => $socialUser->getAvatar(),
                'password' => Hash::make(Str::random(16)),
                'email_verified_at' => now(),
            ]
        );

        return $user;
    }

    public function updatePassword(User $user, string $password): void
    {
        $user->password = Hash::make($password);
        $user->save();
    }
}
