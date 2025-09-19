<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\SendVerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required data
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'LocationSeeder']);
    }

    /**
     * Test client registration
     */
    public function test_client_can_register_successfully()
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/signup/client', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'role',
                    ],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Verify notification was sent
        $user = User::where('email', 'john.doe@example.com')->first();
        Notification::assertSentTo($user, SendVerificationCode::class);
    }

    /**
     * Test lawyer registration
     */
    public function test_lawyer_can_register_successfully()
    {
        Notification::fake();

        $response = $this->postJson('/api/auth/signup/lawyer', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@lawfirm.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'first_name',
                        'last_name',
                        'email',
                        'role',
                    ],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'jane.smith@lawfirm.com',
        ]);

        // Check lawyer profile was created
        $user = User::where('email', 'jane.smith@lawfirm.com')->first();
        $this->assertDatabaseHas('lawyer_profiles', [
            'user_id' => $user->id,
            'status' => 'pending_onboarding',
        ]);

        Notification::assertSentTo($user, SendVerificationCode::class);
    }

    /**
     * Test registration with invalid data
     */
    public function test_registration_fails_with_invalid_data()
    {
        $response = $this->postJson('/api/auth/signup/client', [
            'first_name' => '',
            'last_name' => '',
            'email' => 'invalid-email',
            'password' => '123',
            'password_confirmation' => '456',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email', 'password']);
    }

    /**
     * Test registration with duplicate email
     */
    public function test_registration_fails_with_duplicate_email()
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/auth/signup/client', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'existing@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test successful login
     */
    public function test_user_can_login_with_correct_credentials()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/signin', [
            'email' => 'user@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user',
                    'token',
                ],
            ]);

        $this->assertNotNull($response->json('data.token'));
    }

    /**
     * Test login with incorrect password
     */
    public function test_login_fails_with_incorrect_password()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/auth/signin', [
            'email' => 'user@example.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    /**
     * Test login with unverified email
     */
    public function test_login_fails_with_unverified_email()
    {
        $user = User::factory()->create([
            'email' => 'unverified@example.com',
            'password' => Hash::make('Password123!'),
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/signin', [
            'email' => 'unverified@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Please verify your email first',
            ]);
    }

    /**
     * Test login rate limiting
     */
    public function test_login_is_rate_limited()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        // Attempt login 6 times (limit is 5 per minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/auth/signin', [
                'email' => 'user@example.com',
                'password' => 'WrongPassword',
            ]);
        }

        $response->assertStatus(429); // Too Many Requests
    }

    /**
     * Test email verification
     */
    public function test_user_can_verify_email_with_correct_code()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        // Store verification code
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => '123456',
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Email verified successfully',
            ]);

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    /**
     * Test email verification with invalid code
     */
    public function test_email_verification_fails_with_invalid_code()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => '123456',
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/verify-email', [
            'email' => $user->email,
            'code' => '999999',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired verification code',
            ]);
    }

    /**
     * Test resend verification code
     */
    public function test_user_can_resend_verification_code()
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/auth/resend-verification', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification code sent successfully',
            ]);

        Notification::assertSentTo($user, SendVerificationCode::class);
    }

    /**
     * Test forgot password
     */
    public function test_user_can_request_password_reset()
    {
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset code sent to your email',
            ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    /**
     * Test forgot password with non-existent email
     */
    public function test_forgot_password_fails_with_nonexistent_email()
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'User not found',
            ]);
    }

    /**
     * Test password reset
     */
    public function test_user_can_reset_password_with_valid_token()
    {
        $user = User::factory()->create();

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => '123456',
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset successfully',
            ]);

        // Verify password was changed
        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword123!', $user->password));
    }

    /**
     * Test password reset with invalid token
     */
    public function test_password_reset_fails_with_invalid_token()
    {
        $user = User::factory()->create();

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => '123456',
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '999999',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired reset code',
            ]);
    }

    /**
     * Test password reset with expired token
     */
    public function test_password_reset_fails_with_expired_token()
    {
        $user = User::factory()->create();

        DB::table('password_reset_tokens')->insert([
            'email' => $user->email,
            'token' => '123456',
            'created_at' => now()->subHours(2), // Token expired (older than 1 hour)
        ]);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'code' => '123456',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid or expired reset code',
            ]);
    }

    /**
     * Test user logout
     */
    public function test_authenticated_user_can_logout()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/signout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);

        // Verify tokens are revoked
        $this->assertCount(0, $user->tokens);
    }

    /**
     * Test logout requires authentication
     */
    public function test_logout_requires_authentication()
    {
        $response = $this->postJson('/api/signout');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    /**
     * Test authenticated user can access protected route
     */
    public function test_authenticated_user_can_access_protected_route()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'email' => $user->email,
            ]);
    }

    /**
     * Test unauthenticated user cannot access protected route
     */
    public function test_unauthenticated_user_cannot_access_protected_route()
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    /**
     * Test password validation rules
     */
    public function test_password_must_meet_security_requirements()
    {
        $weakPasswords = [
            'short',           // Too short
            'nouppercase123!', // No uppercase
            'NOLOWERCASE123!', // No lowercase
            'NoNumbers!',      // No numbers
            'NoSpecialChar1',  // No special characters
        ];

        foreach ($weakPasswords as $password) {
            $response = $this->postJson('/api/auth/signup/client', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'test@example.com',
                'password' => $password,
                'password_confirmation' => $password,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['password']);
        }
    }

    /**
     * Test social login redirect
     */
    public function test_social_login_redirect_url_is_generated()
    {
        $providers = ['google', 'linkedin'];

        foreach ($providers as $provider) {
            $response = $this->getJson("/api/auth/social/{$provider}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'url',
                    ],
                ]);

            $this->assertStringContainsString($provider, $response->json('data.url'));
        }
    }

    /**
     * Test invalid social provider
     */
    public function test_invalid_social_provider_returns_error()
    {
        $response = $this->getJson('/api/auth/social/invalid-provider');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid provider',
            ]);
    }

    /**
     * Test resend verification with rate limiting
     */
    public function test_resend_verification_is_rate_limited()
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);

        Sanctum::actingAs($user);

        // Attempt to resend 7 times (limit is 6 per minute)
        for ($i = 0; $i < 7; $i++) {
            $response = $this->postJson('/api/email/resend-verification', [
                'email' => $user->email,
            ]);
        }

        $response->assertStatus(429); // Too Many Requests
    }

    /**
     * Test user data is properly sanitized on registration
     */
    public function test_user_data_is_sanitized_on_registration()
    {
        $response = $this->postJson('/api/auth/signup/client', [
            'first_name' => '  John  ',
            'last_name' => '  Doe  ',
            'email' => '  JOHN.DOE@EXAMPLE.COM  ',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com', // Should be lowercased and trimmed
        ]);
    }
}
