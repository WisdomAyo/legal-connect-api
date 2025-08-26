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


        return User::create([
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone_number' => $data['phone_number'],
            'country_id' => $data['country_id'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'password' => $data['password'],
            'profile_picture' => $data['profile_picture'] ?? null,
            'last_seen_at' => now(),
        ]);
    }


    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
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
