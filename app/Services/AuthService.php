<?php

// app/Services/AuthService.php

namespace App\Services;

use App\Enums\Role;
use App\Events\UserRegistered;
use App\Exceptions\AuthenticationException;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\SendVerificationCode;
use App\Repositories\LawyerRepository;
use App\Repositories\UserRepository;
use App\Services\Security\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * AuthService - Handles all authentication business logic
 *
 * Design Decision: Service Layer Pattern
 * Why: Separates business logic from controllers, making the code:
 * - More testable (can test without HTTP context)
 * - Reusable (can use in commands, jobs, etc.)
 * - Maintainable (single responsibility)
 */
class AuthService
{
    public function __construct(
        protected UserRepository $userRepository,
        protected LawyerRepository $lawyerRepository,
        protected AuditService $auditService
    ) {}

    /**
     * Handle user registration (both client and lawyer)
     *
     * @param  array  $data  Validated input data
     * @param  string  $role  User role (client or lawyer)
     * @return array Response with user, token, and status
     *
     * Why DB Transaction: Ensures atomicity - if any step fails, everything rolls back
     * This prevents partial data creation which could leave the system in an inconsistent state
     */
    public function signup(array $data, string $role): array
    {
        // Rate limiting check - prevent spam registrations
        $this->checkSignupRateLimit();

        DB::beginTransaction();

        try {
            $data['password'] = Hash::make($data['password']);
            $user = $this->userRepository->createUser($data);
            $user->assignRole($role);
            if ($role === Role::Lawyer->value) {
                $this->lawyerRepository->createInitialProfile($user, $data);
            }

            $this->sendVerificationCode($user);

            // Step 6: Fire event for additional processing
            // Events allow decoupled side effects (emails, notifications, etc.)
            event(new UserRegistered($user));

            // Step 7: Create authentication token
            $token = $this->createAuthToken($user);

            $this->auditService->log('user_registered', $user, [
                'role' => $role,
                'ip' => request()->ip(),
            ]);

            // Step 8: Log the registration for audit purposes
            Log::info('User registered', [
                'user_id' => $user->id,
                'role' => $role,
                'ip' => request()->ip(),
            ]);

            DB::commit();
            $user->load('lawyerProfile', 'city.state.country');

            return [
                'success' => true,
                'message' => 'Registration successful. Please check your email for verification code.',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration') * 60, // Convert to seconds
                    'requires_onboarding' => $role === Role::Lawyer->value,
                ],
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            // Log error for debugging
            Log::error('Signup failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle user login with comprehensive security checks
     *
     * Security measures implemented:
     * - Rate limiting (prevent brute force)
     * - Account locking (after failed attempts)
     * - Email verification check
     * - Lawyer-specific status checks
     */
    public function signin(array $credentials): array
    {
        try {
            // Check rate limits for both email and IP
            $this->checkLoginRateLimit($credentials['email']);

            // Find user by email
            $user = $this->userRepository->findByEmail($credentials['email']);

            // Verify credentials
            if (! $user || ! Hash::check($credentials['password'], $user->password)) {
                $this->auditService->logFailedLogin($credentials['email'], request()->ip());

                if ($user) {
                    $this->handleFailedLogin($user);
                }

                Log::warning('Failed login attempt', [
                    'email' => $credentials['email'],
                    'ip' => request()->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Invalid credentials'
                ];
            }

            // Check if account is locked
            if ($this->isAccountLocked($user)) {
                return [
                    'success' => false,
                    'message' => 'Account temporarily locked'
                ];
            }

            // Check if account is active
            if (! $user->is_active) {
                return [
                    'success' => false,
                    'message' => 'Account deactivated'
                ];
            }

            // Check email verification
            if (! $user->hasVerifiedEmail()) {
                $this->sendVerificationCode($user);
                return [
                    'success' => false,
                    'message' => 'Please verify your email first'
                ];
            }

            // Check lawyer-specific restrictions
            if ($user->hasRole('lawyer')) {
                $this->validateLawyerLogin($user);
            }

            // Login successful - reset failed attempts
            $this->resetFailedLoginAttempts($user);

            // Update login tracking information
            $user->update([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
                'last_seen_at' => now(),
            ]);

            // Create authentication token
            $token = $this->createAuthToken($user);

            $this->auditService->log('user_login', $user, [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            Log::info('User logged in', [
                'user_id' => $user->id,
                'ip' => request()->ip(),
            ]);

            // Load relationships
            $user->load('lawyerProfile', 'city.state.country');

            // Determine if onboarding is needed
            $requiresOnboarding = false;
            if ($user->hasRole('lawyer')) {
                $profileStatus = $user->lawyerProfile?->status;
                $requiresOnboarding = in_array($profileStatus, ['pending_onboarding', 'in_progress']);
            }

            return [
                'success' => true,
                'message' => 'Login successful.',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => config('sanctum.expiration') * 60,
                    'requires_onboarding' => $requiresOnboarding,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Signin error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'message' => 'Authentication failed',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ];
        }
    }

    public function gotoProvider(string $provider): array
    {
        // Validate provider
        if (! in_array($provider, ['google', 'linkedin'])) {
            return [
                'success' => false,
                'message' => 'Invalid provider'
            ];
        }

        try {
            $redirectUrl = Socialite::driver($provider)
                ->stateless()
                ->redirect()
                ->getTargetUrl();

            return [
                'success' => true,
                'data' => ['url' => $redirectUrl]
            ];
        } catch (\Exception $e) {
            Log::error('Social auth redirect failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Provider configuration error.'
            ];
        }
    }

    /**
     * Handle social authentication callback
     */
    public function socialSignIn(string $provider): array
    {
        try {
            $socialUser = Socialite::driver($provider)
                ->stateless()
                ->user();
        } catch (\Exception $e) {
            Log::error('Social authentication failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            throw new AuthenticationException('Social authentication failed: '.$e->getMessage());
        }

        // Find or create user
        $user = $this->userRepository->findByEmail($socialUser->getEmail());

        if ($user) {
            // Update social provider ID
            $this->userRepository->findOrCreateFromSocial($user, $provider, $socialUser->getId());
        } else {
            // Create new user from social data
            $user = $this->userRepository->findOrCreateFromSocial($socialUser);

            // Assign default role (client)
            $user->assignRole('client');
        }

        // Load relationships
        $user->load('city.state.country', 'lawyerProfile');

        // Create API token
        $token = $this->createAuthToken($user);

        Log::info('Social sign-in successful', [
            'user_id' => $user->id,
            'provider' => $provider,
        ]);

        return [
            'message' => 'Social sign-in successful.',
            'user' => new UserResource($user),
            'token' => $token,
            'requires_onboarding' => $user->hasRole('lawyer') &&
                $user->lawyerProfile?->status === 'pending_onboarding',
        ];
    }

    /**
     * Handle email verification
     */
    public function verifyUserEmail(array $data): array
    {
        $user = $this->userRepository->findByEmail($data['email']);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No user found with this email address.'],
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return [
                'success' => true,
                'message' => 'Email already verified.',
                'data' => null,
            ];
        }

        // Check if code is valid and not expired
        if (! $user->email_verification_code) {
            throw ValidationException::withMessages([
                'code' => ['Verification code is invalid or has expired.'],
            ]);
        }

        // Verify the code (comparing hashed version for security)
        if (! Hash::check($data['code'], $user->email_verification_code)) {
            throw ValidationException::withMessages([
                'code' => ['The provided verification code is incorrect.'],
            ]);
        }

        // Mark email as verified
        $user->markEmailAsVerified();
        $user->update([
            'email_verification_code' => null,
            'email_verification_code_expires_at' => null,
        ]);

        // Create token for auto-login after verification
        $token = $this->createAuthToken($user);

        // Audit log
        $this->auditService->log('email_verified', $user);

        // Log verification
        Log::info('Email verified', ['user_id' => $user->id]);

        // Load relationships
        $user->load('lawyerProfile', 'city.state.country');

        return [
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => [
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => config('sanctum.expiration') * 60,
                'requires_onboarding' => $user->hasRole('lawyer') &&
                    $user->lawyerProfile?->status === 'pending_onboarding',
            ],
        ];
    }

    /**
     * Handle user logout
     */
    public function signout(User $user): array
    {
        // Revoke the current access token
        // Why: Prevents token reuse after logout
        $user->currentAccessToken()->delete();

        $this->auditService->log('user_logout', $user);
        // Log the logout
        Log::info('User logged out', ['user_id' => $user->id]);

        return [
            'success' => true,
            'message' => 'Logged out successfully',
            'data' => null,
        ];
    }

    /**
     * Handle password reset request
     */
    public function forgotPassword(array $credentials): array
    {
        // Rate limiting to prevent abuse
        $this->checkPasswordResetRateLimit($credentials['email']);

        // Send reset link using Laravel's built-in password reset
        $status = Password::sendResetLink(['email' => $credentials['email']]);

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        $this->auditService->logPasswordResetRequest($credentials['email'], request()->ip());

        // Log password reset request
        Log::info('Password reset requested', ['email' => $credentials['email']]);

        return [
            'success' => true,
            'message' => 'Password reset code sent to your email',
            'data' => null,
        ];
    }

    /**
     * Handle password reset
     */
    public function resetPassword(array $credentials): array
    {
        // Map 'code' to 'token' for Laravel's Password facade
        $resetData = [
            'email' => $credentials['email'],
            'token' => $credentials['code'],
            'password' => $credentials['password'],
            'password_confirmation' => $credentials['password_confirmation'] ?? $credentials['password'],
        ];

        $status = Password::reset(
            $resetData,
            function (User $user, string $password) {
                $user->update(['password' => Hash::make($password)]);

                // Revoke all tokens for security
                $user->tokens()->delete();

                $this->auditService->log('password_reset', $user);
                Log::info('Password reset', ['user_id' => $user->id]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired reset code'],
            ]);
        }

        return [
            'success' => true,
            'message' => 'Password reset successfully',
            'data' => null,
        ];
    }

    /**
     * Resend verification code
     */
    public function resendVerificationLink(array $data): array
    {
        // Rate limiting
        $this->checkVerificationResendRateLimit($data['email']);

        $user = $this->userRepository->findByEmail($data['email']);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['No user found with this email address.'],
            ]);
        }

        if ($user->hasVerifiedEmail()) {
            return [
                'success' => true,
                'message' => 'Your email is already verified.',
                'data' => null,
            ];
        }

        // Send new verification code
        $this->sendVerificationCode($user);

        return [
            'success' => true,
            'message' => 'Verification code sent successfully',
            'data' => null,
        ];
    }

    /**
     * PRIVATE HELPER METHODS
     */

    /**
     * Generate and send verification code
     */
    private function sendVerificationCode(User $user): void
    {
        // Generate 6-digit code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store hashed code for security (never store plain text sensitive data)
        $user->update([
            'email_verification_code' => Hash::make($code),
            'email_verification_code_expires_at' => now()->addMinutes(15),
        ]);

        // Send notification
        $user->notify(new SendVerificationCode($code));
    }

    /**
     * Create authentication token
     */
    private function createAuthToken(User $user): string
    {
        // Define token abilities based on role
        $abilities = $user->hasRole('lawyer')
            ? ['lawyer', 'profile:read', 'profile:write']
            : ['client', 'profile:read'];

        // Create token with expiration
        $token = $user->createToken(
            'auth-token',
            $abilities,
            now()->addDays(30)
        );

        return $token->plainTextToken;
    }

    /**
     * Validate lawyer-specific login requirements
     */
    private function validateLawyerLogin(User $user): void
    {
        $profileStatus = $user->lawyerProfile?->status;

        // Check for rejected or suspended profiles
        if ($profileStatus === 'rejected') {
            throw new AccessDeniedHttpException(
                'Your lawyer application has been rejected. Please contact support for more information.'
            );
        }

        if ($profileStatus === 'suspended') {
            throw new AccessDeniedHttpException(
                'Your lawyer profile has been suspended. Please contact support.'
            );
        }
    }

    /**
     * Handle failed login attempt
     */
    private function handleFailedLogin(User $user): void
    {
        $user->increment('failed_login_attempts');

        // Lock account after 5 failed attempts
        if ($user->failed_login_attempts >= 5) {
            $user->update(['locked_until' => now()->addMinutes(30)]);
        }
    }

    /**
     * Check if account is locked
     */
    private function isAccountLocked(User $user): bool
    {
        return $user->locked_until && $user->locked_until->isFuture();
    }

    /**
     * Reset failed login attempts
     */
    private function resetFailedLoginAttempts(User $user): void
    {
        $user->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }

    /**
     * RATE LIMITING METHODS
     */
    private function checkSignupRateLimit(): void
    {
        $key = 'signup:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw new AuthenticationException(
                "Too many signup attempts. Please try again in {$seconds} seconds."
            );
        }

        RateLimiter::hit($key, 3600); // 1 hour
    }

    private function checkLoginRateLimit(string $email): void
    {
        $emailKey = 'login:email:'.$email;
        $ipKey = 'login:ip:'.request()->ip();

        if (
            RateLimiter::tooManyAttempts($emailKey, 5) ||
            RateLimiter::tooManyAttempts($ipKey, 10)
        ) {
            $seconds = max(
                RateLimiter::availableIn($emailKey),
                RateLimiter::availableIn($ipKey)
            );
            throw new AuthenticationException(
                "Too many login attempts. Please try again in {$seconds} seconds."
            );
        }

        RateLimiter::hit($emailKey, 900); // 15 minutes
        RateLimiter::hit($ipKey, 900);
    }

    private function checkPasswordResetRateLimit(string $email): void
    {
        $key = 'password_reset:'.$email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw new AuthenticationException(
                "Too many password reset attempts. Please try again in {$seconds} seconds."
            );
        }

        RateLimiter::hit($key, 3600); // 1 hour
    }

    private function checkVerificationResendRateLimit(string $email): void
    {
        $key = 'verification_resend:'.$email;

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            throw new AuthenticationException(
                "Too many verification attempts. Please try again in {$seconds} seconds."
            );
        }

        RateLimiter::hit($key, 900); // 15 minutes
    }
}
