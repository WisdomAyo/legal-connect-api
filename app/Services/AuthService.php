<?php

namespace App\Services;

use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use App\Repositories\LawyerRepository;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Password;
use App\Models\User;
use App\Enums\Role;
use App\Events\UserRegistered;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

   class AuthService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected LawyerRepository $lawyerRepository,
    ) {}

     public function signup(array $data, string $role): JsonResponse
    {
        $data['password'] = Hash::make($data['password']);
        $user = $this->userRepository->createUser($data);
        $user->assignRole($role);
        // If the user is a lawyer, create their initial, pending profile.
        if ($role === Role::Lawyer->value) {
            $this->lawyerRepository->createInitialProfile($user, $data);
        }

        event(new UserRegistered($user));
        $token = $this->createApiToken($user);
        $user->load('LawyerProfile');

        // Load necessary relationships before creating the resource
        $user->load('city.state.country', 'lawyerProfile');

        return response()->json([
            'message' => 'Signup successful. Please check your email to verify your account.',
            'user' => new UserResource($user), // Use a resource for consistent output
            'token' => $token
        ], 201);
    }

    /**
     * Handles user sign-in.
     */
    public function signin(array $credentials): JsonResponse
    {
        $user = $this->userRepository->findByEmail($credentials['email']);

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json(['error' => 'The provided credentials do not match our records.'], 401);
        }

        if ($user->hasRole('lawyer')) {
            $profileStatus = $user->lawyerProfile?->status;

            // Block login if lawyer's profile is not fully verified by an admin.
            if ($profileStatus !== 'verified') {
                $message = 'Your account is not yet active.';
                // Provide a more specific message to guide the user.
                if ($profileStatus === 'pending_onboarding' || $profileStatus === 'pending_review') {
                    $message = 'Please complete your profile onboarding process to activate your account.';
                } elseif ($profileStatus === 'rejected') {
                    $message = 'Your application has been rejected. Please contact support for assistance.';
                }
                return response()->json(['error' => $message], 403);
            }
        }

        $token = $this->createApiToken($user);
        $user->load('city.state.country', 'lawyerProfile');

        return response()->json([
            'message' => 'Sign-in successful.',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function signout(User $user): array
    {
        $user->tokens()->delete(); // Revoke all tokens for the user
        return ['message' => 'Signed out'];
    }

    public function verifyUserEmail(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }
        $request->user()->markEmailAsVerified();
        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function resendVerificationLink(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }
        $request->user()->sendEmailVerificationNotification();
        return response()->json(['message' => 'Verification link sent successfully.']);
    }

    // === PASSWORD RESET ===
    public function forgotPassword(array $credentials): JsonResponse
    {
        $status = Password::sendResetLink($credentials);
        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => __($status)])
            : response()->json(['error' => __($status)], 422);
    }

    public function resetPassword(array $credentials): JsonResponse
    {
        $status = Password::reset($credentials, function (User $user, string $password) {
            $this->userRepository->updatePassword($user, $password);
        });
        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)])
            : response()->json(['error' => __($status)], 422);
    }


    public function gotoProvider(string $provider): JsonResponse
    {
        try {
            $redirectUrl = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();
            return response()->json(['redirect_url' => $redirectUrl]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Provider not supported.'], 404);
        }
    }

    public function socialSignIn(string $provider): JsonResponse
    {
        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Authentication failed. Please try again.'], 401);
        }

        // Find or create user
        $user = $this->userRepository->findOrCreateFromSocial($socialUser);

        // Ensure the user has the 'client' role if they are new
        if (!$user->hasAnyRole()) {
            $user->assignRole('client');
        }

        // Create an API token
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'message' => 'Social sign-in successful.',
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    private function createApiToken(User $user): string
    {
        $scopes = $user->hasRole('lawyer') ? ['lawyer', 'profile:read'] : ['client'];
        return $user->createToken('api-token', $scopes)->plainTextToken;
    }
}


