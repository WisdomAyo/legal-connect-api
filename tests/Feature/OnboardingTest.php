<?php

namespace Tests\Feature;

use App\Events\OnboardingCompleted;
use App\Events\OnboardingStepCompleted;
use App\Models\City;
use App\Models\Country;
use App\Models\Language;
use App\Models\LawyerProfile;
use App\Models\OnboardingStep;
use App\Models\PracticeArea;
use App\Models\Specialization;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected User $lawyer;

    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed required data
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'LocationSeeder']);
        $this->artisan('db:seed', ['--class' => 'LegalDataSeeder']);

        // Create test users
        $this->lawyer = User::factory()->create();
        $this->lawyer->assignRole('lawyer');
        LawyerProfile::create([
            'user_id' => $this->lawyer->id,
            'status' => 'draft',
        ]);

        $this->client = User::factory()->create();
        $this->client->assignRole('client');
    }

    /**
     * Test only lawyers can access onboarding endpoints
     */
    public function test_only_lawyers_can_access_onboarding_endpoints()
    {
        Sanctum::actingAs($this->client);

        $response = $this->getJson('/api/lawyer/onboarding/status');

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'User does not have the right roles.',
            ]);
    }

    /**
     * Test unauthenticated users cannot access onboarding
     */
    public function test_unauthenticated_users_cannot_access_onboarding()
    {
        $response = $this->getJson('/api/lawyer/onboarding/status');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }

    /**
     * Test get onboarding status
     */
    public function test_lawyer_can_get_onboarding_status()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->getJson('/api/lawyer/onboarding/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'overall_progress',
                    'completed_steps',
                    'total_steps',
                    'current_step',
                    'steps',
                    'can_submit',
                    'profile_status',
                    'estimated_completion_time',
                ],
            ]);

        $this->assertEquals('draft', $response->json('data.profile_status'));
        $this->assertEquals(0, $response->json('data.overall_progress'));
        $this->assertFalse($response->json('data.can_submit'));
    }

    /**
     * Test get all steps metadata
     */
    public function test_lawyer_can_get_all_steps_metadata()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->getJson('/api/lawyer/onboarding/steps');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'name',
                        'order',
                        'title',
                        'description',
                        'required',
                        'skippable',
                        'icon',
                        'validation_rules',
                    ],
                ],
            ]);

        $steps = $response->json('data');
        $this->assertCount(4, $steps); // 4 onboarding steps
    }

    /**
     * Test get specific step metadata
     */
    public function test_lawyer_can_get_specific_step_metadata()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->getJson('/api/lawyer/onboarding/steps/personal_info/metadata');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'name',
                    'order',
                    'title',
                    'description',
                    'required',
                    'skippable',
                    'icon',
                    'validation_rules',
                ],
            ]);

        $this->assertEquals('personal_info', $response->json('data.name'));
        $this->assertTrue($response->json('data.required'));
        $this->assertFalse($response->json('data.skippable'));
    }

    /**
     * Test get invalid step metadata returns error
     */
    public function test_get_invalid_step_metadata_returns_error()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->getJson('/api/lawyer/onboarding/steps/invalid_step/metadata');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['step']);
    }

    /**
     * Test get step validation rules
     */
    public function test_lawyer_can_get_step_validation_rules()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->getJson('/api/lawyer/onboarding/steps/personal_info/validation-rules');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'phone_number',
                    'country_id',
                    'state_id',
                    'city_id',
                    'office_address',
                    'bio',
                ],
            ]);
    }

    /**
     * Test get step data
     */
    public function test_lawyer_can_get_step_data()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->getJson('/api/lawyer/onboarding/steps/personal_info/data');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'step',
                    'saved_data',
                    'profile_data',
                    'is_completed',
                    'is_skipped',
                ],
            ]);

        $this->assertEquals('personal_info', $response->json('data.step'));
        $this->assertFalse($response->json('data.is_completed'));
    }

    /**
     * Test save personal info step
     */
    public function test_lawyer_can_save_personal_info_step()
    {
        Event::fake();
        Sanctum::actingAs($this->lawyer);

        $country = Country::first();
        $state = State::where('country_id', $country->id)->first();
        $city = City::where('state_id', $state->id)->first();

        $response = $this->postJson('/api/lawyer/onboarding/steps/personal_info', [
            'phone_number' => '+2348012345678',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
            'office_address' => '123 Legal Street, Victoria Island, Lagos',
            'bio' => 'Experienced lawyer with 10 years of practice',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Step saved successfully',
            ])
            ->assertJsonStructure([
                'data' => [
                    'completed_step',
                    'next_step',
                    'overall_progress',
                    'can_submit',
                ],
            ]);

        $this->assertEquals('personal_info', $response->json('data.completed_step'));
        $this->assertEquals('professional_info', $response->json('data.next_step'));

        // Verify data was saved
        $this->assertDatabaseHas('users', [
            'id' => $this->lawyer->id,
            'phone_number' => '+2348012345678',
            'country_id' => $country->id,
            'state_id' => $state->id,
            'city_id' => $city->id,
        ]);

        $this->assertDatabaseHas('lawyer_profiles', [
            'user_id' => $this->lawyer->id,
            'office_address' => '123 Legal Street, Victoria Island, Lagos',
            'bio' => 'Experienced lawyer with 10 years of practice',
        ]);

        $this->assertDatabaseHas('onboarding_steps', [
            'user_id' => $this->lawyer->id,
            'step_name' => 'personal_info',
            'is_completed' => true,
        ]);

        Event::assertDispatched(OnboardingStepCompleted::class, function ($event) {
            return $event->user->id === $this->lawyer->id && $event->step === 'personal_info';
        });
    }

    /**
     * Test save personal info with invalid data
     */
    public function test_save_personal_info_fails_with_invalid_data()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->postJson('/api/lawyer/onboarding/steps/personal_info', [
            'phone_number' => 'invalid-phone',
            'country_id' => 999999,
            'state_id' => 999999,
            'city_id' => 999999,
            'office_address' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'phone_number',
                'country_id',
                'state_id',
                'city_id',
                'office_address',
            ]);
    }

    /**
     * Test save professional info step
     */
    public function test_lawyer_can_save_professional_info_step()
    {
        Event::fake();
        Sanctum::actingAs($this->lawyer);

        $practiceAreas = PracticeArea::take(3)->pluck('id')->toArray();
        $specializations = Specialization::take(2)->pluck('id')->toArray();
        $languages = Language::take(2)->pluck('id')->toArray();

        $response = $this->postJson('/api/lawyer/onboarding/steps/professional_info', [
            'nba_enrollment_number' => 'NBA/2015/123456',
            'year_of_call' => 2015,
            'law_school' => 'Nigerian Law School, Lagos',
            'graduation_year' => 2014,
            'practice_areas' => $practiceAreas,
            'specializations' => $specializations,
            'languages' => $languages,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Step saved successfully',
            ]);

        // Verify data was saved
        $this->assertDatabaseHas('lawyer_profiles', [
            'user_id' => $this->lawyer->id,
            'nba_enrollment_number' => 'NBA/2015/123456',
            'year_of_call' => 2015,
            'law_school' => 'Nigerian Law School, Lagos',
            'graduation_year' => 2014,
        ]);

        // Verify relationships
        $profile = LawyerProfile::where('user_id', $this->lawyer->id)->first();
        $this->assertCount(3, $profile->practiceAreas);
        $this->assertCount(2, $profile->specializations);
        $this->assertCount(2, $profile->languages);

        Event::assertDispatched(OnboardingStepCompleted::class);
    }

    /**
     * Test save professional info with duplicate NBA number
     */
    public function test_save_professional_info_fails_with_duplicate_nba_number()
    {
        Sanctum::actingAs($this->lawyer);

        // Create another lawyer with NBA number
        $otherLawyer = User::factory()->create();
        $otherLawyer->assignRole('lawyer');
        LawyerProfile::create([
            'user_id' => $otherLawyer->id,
            'nba_enrollment_number' => 'NBA/2015/999999',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/lawyer/onboarding/steps/professional_info', [
            'nba_enrollment_number' => 'NBA/2015/999999', // Duplicate
            'year_of_call' => 2015,
            'law_school' => 'Nigerian Law School, Lagos',
            'graduation_year' => 2014,
            'practice_areas' => [1],
            'languages' => [1],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nba_enrollment_number']);
    }

    /**
     * Test save documents step
     */
    public function test_lawyer_can_save_documents_step()
    {
        Event::fake();
        Storage::fake('private');
        Sanctum::actingAs($this->lawyer);

        $nbaCertificate = UploadedFile::fake()->create('nba_certificate.pdf', 1000);
        $cv = UploadedFile::fake()->create('cv.pdf', 2000);

        $response = $this->postJson('/api/lawyer/onboarding/steps/documents', [
            'nba_certificate' => $nbaCertificate,
            'cv' => $cv,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Step saved successfully',
            ]);

        // Verify files were stored
        $this->assertTrue(Storage::disk('private')->exists("lawyers/{$this->lawyer->id}/documents/{$nbaCertificate->hashName()}"));
        $this->assertTrue(Storage::disk('private')->exists("lawyers/{$this->lawyer->id}/documents/{$cv->hashName()}"));

        // Verify database was updated
        $profile = LawyerProfile::where('user_id', $this->lawyer->id)->first();
        $this->assertNotNull($profile->bar_certificate_path);
        $this->assertNotNull($profile->cv_path);

        Event::assertDispatched(OnboardingStepCompleted::class);
    }

    /**
     * Test save documents with invalid file types
     */
    public function test_save_documents_fails_with_invalid_file_types()
    {
        Storage::fake('private');
        Sanctum::actingAs($this->lawyer);

        $invalidFile = UploadedFile::fake()->create('document.exe', 1000);

        $response = $this->postJson('/api/lawyer/onboarding/steps/documents', [
            'nba_certificate' => $invalidFile,
            'cv' => $invalidFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nba_certificate', 'cv']);
    }

    /**
     * Test save documents with oversized files
     */
    public function test_save_documents_fails_with_oversized_files()
    {
        Storage::fake('private');
        Sanctum::actingAs($this->lawyer);

        $largeFile = UploadedFile::fake()->create('large.pdf', 6000); // 6MB (limit is 5MB)

        $response = $this->postJson('/api/lawyer/onboarding/steps/documents', [
            'nba_certificate' => $largeFile,
            'cv' => $largeFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nba_certificate', 'cv']);
    }

    /**
     * Test save availability step
     */
    public function test_lawyer_can_save_availability_step()
    {
        Event::fake();
        Sanctum::actingAs($this->lawyer);

        $availability = [
            'monday' => ['start' => '09:00', 'end' => '17:00'],
            'tuesday' => ['start' => '09:00', 'end' => '17:00'],
            'wednesday' => ['start' => '09:00', 'end' => '17:00'],
            'thursday' => ['start' => '09:00', 'end' => '17:00'],
            'friday' => ['start' => '09:00', 'end' => '15:00'],
        ];

        $response = $this->postJson('/api/lawyer/onboarding/steps/availability', [
            'hourly_rate' => 50000,
            'consultation_fee' => 10000,
            'availability' => $availability,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Step saved successfully',
            ]);

        // Verify data was saved
        $this->assertDatabaseHas('lawyer_profiles', [
            'user_id' => $this->lawyer->id,
            'hourly_rate' => 50000,
            'consultation_fee' => 10000,
        ]);

        $profile = LawyerProfile::where('user_id', $this->lawyer->id)->first();
        $this->assertEquals($availability, $profile->availability);

        Event::assertDispatched(OnboardingStepCompleted::class);
    }

    /**
     * Test save availability with invalid time format
     */
    public function test_save_availability_fails_with_invalid_time_format()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->postJson('/api/lawyer/onboarding/steps/availability', [
            'consultation_fee' => 10000,
            'availability' => [
                'monday' => ['start' => '25:00', 'end' => '17:00'], // Invalid time
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['availability.monday.start']);
    }

    /**
     * Test save availability with end time before start time
     */
    public function test_save_availability_fails_with_end_time_before_start()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->postJson('/api/lawyer/onboarding/steps/availability', [
            'consultation_fee' => 10000,
            'availability' => [
                'monday' => ['start' => '17:00', 'end' => '09:00'], // End before start
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['availability.monday.end']);
    }

    /**
     * Test skip optional step
     */
    public function test_lawyer_can_skip_optional_step()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->postJson('/api/lawyer/onboarding/steps/availability/skip', [
            'reason' => 'Will complete later',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Step skipped',
            ])
            ->assertJsonStructure([
                'data' => [
                    'skipped_step',
                    'next_step',
                ],
            ]);

        $this->assertDatabaseHas('onboarding_steps', [
            'user_id' => $this->lawyer->id,
            'step_name' => 'availability',
            'is_skipped' => true,
        ]);
    }

    /**
     * Test cannot skip required step
     */
    public function test_lawyer_cannot_skip_required_step()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->postJson('/api/lawyer/onboarding/steps/personal_info/skip', [
            'reason' => 'Want to skip',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['step']);
    }

    /**
     * Test submit profile for review with all steps completed
     */
    public function test_lawyer_can_submit_profile_when_all_required_steps_completed()
    {
        Event::fake();
        Sanctum::actingAs($this->lawyer);

        // Complete all required steps
        $this->completeAllRequiredSteps();

        $response = $this->postJson('/api/lawyer/onboarding/submit');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Your profile has been submitted for review',
                'data' => [
                    'status' => 'pending_review',
                    'estimated_review_time' => '24-48 hours',
                    'notification' => 'You will receive an email once your profile is reviewed',
                ],
            ]);

        $this->assertDatabaseHas('lawyer_profiles', [
            'user_id' => $this->lawyer->id,
            'status' => 'pending_review',
        ]);

        Event::assertDispatched(OnboardingCompleted::class, function ($event) {
            return $event->user->id === $this->lawyer->id;
        });
    }

    /**
     * Test cannot submit profile with incomplete required steps
     */
    public function test_lawyer_cannot_submit_profile_with_incomplete_steps()
    {
        Sanctum::actingAs($this->lawyer);

        // Complete only one step
        OnboardingStep::create([
            'user_id' => $this->lawyer->id,
            'step_name' => 'personal_info',
            'is_completed' => true,
            'step_data' => [],
        ]);

        $response = $this->postJson('/api/lawyer/onboarding/submit');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile']);

        $this->assertDatabaseHas('lawyer_profiles', [
            'user_id' => $this->lawyer->id,
            'status' => 'draft', // Status should not change
        ]);
    }

    /**
     * Test onboarding progress calculation
     */
    public function test_onboarding_progress_is_calculated_correctly()
    {
        Sanctum::actingAs($this->lawyer);

        // Initial progress should be 0
        $response = $this->getJson('/api/lawyer/onboarding/status');
        $this->assertEquals(0, $response->json('data.overall_progress'));

        // Complete first step (25% progress)
        OnboardingStep::create([
            'user_id' => $this->lawyer->id,
            'step_name' => 'personal_info',
            'is_completed' => true,
            'step_data' => [],
        ]);

        $response = $this->getJson('/api/lawyer/onboarding/status');
        $this->assertEquals(25, $response->json('data.overall_progress'));

        // Complete second step (50% progress)
        OnboardingStep::create([
            'user_id' => $this->lawyer->id,
            'step_name' => 'professional_info',
            'is_completed' => true,
            'step_data' => [],
        ]);

        $response = $this->getJson('/api/lawyer/onboarding/status');
        $this->assertEquals(50, $response->json('data.overall_progress'));

        // Skip optional step (75% progress)
        OnboardingStep::create([
            'user_id' => $this->lawyer->id,
            'step_name' => 'availability',
            'is_skipped' => true,
            'step_data' => [],
        ]);

        $response = $this->getJson('/api/lawyer/onboarding/status');
        $this->assertEquals(75, $response->json('data.overall_progress'));
    }

    /**
     * Test get practice areas endpoint
     */
    public function test_authenticated_user_can_get_practice_areas()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->getJson('/api/practice-areas');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name'],
            ]);
    }

    /**
     * Test get specializations endpoint
     */
    public function test_authenticated_user_can_get_specializations()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->getJson('/api/specializations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name'],
            ]);
    }

    /**
     * Test get languages endpoint
     */
    public function test_authenticated_user_can_get_languages()
    {
        Sanctum::actingAs($this->lawyer);

        $response = $this->getJson('/api/languages');

        $response->assertStatus(200)
            ->assertJsonStructure([
                '*' => ['id', 'name'],
            ]);
    }

    /**
     * Test helper endpoints require authentication
     */
    public function test_helper_endpoints_require_authentication()
    {
        $endpoints = [
            '/api/practice-areas',
            '/api/specializations',
            '/api/languages',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $response->assertStatus(401);
        }
    }

    /**
     * Helper method to complete all required steps
     */
    private function completeAllRequiredSteps()
    {
        // Personal info
        OnboardingStep::create([
            'user_id' => $this->lawyer->id,
            'step_name' => 'personal_info',
            'is_completed' => true,
            'step_data' => [
                'phone_number' => '+2348012345678',
                'office_address' => 'Test Address',
            ],
        ]);

        // Professional info
        OnboardingStep::create([
            'user_id' => $this->lawyer->id,
            'step_name' => 'professional_info',
            'is_completed' => true,
            'step_data' => [
                'nba_enrollment_number' => 'NBA/2015/123456',
                'year_of_call' => 2015,
            ],
        ]);

        // Documents
        OnboardingStep::create([
            'user_id' => $this->lawyer->id,
            'step_name' => 'documents',
            'is_completed' => true,
            'step_data' => [
                'nba_certificate' => 'path/to/certificate.pdf',
                'cv' => 'path/to/cv.pdf',
            ],
        ]);

        // Update lawyer profile with required data
        $this->lawyer->lawyerProfile->update([
            'nba_enrollment_number' => 'NBA/2015/123456',
            'year_of_call' => 2015,
            'law_school' => 'Test Law School',
            'graduation_year' => 2014,
            'bar_certificate_path' => 'path/to/certificate.pdf',
            'cv_path' => 'path/to/cv.pdf',
        ]);
    }
}
