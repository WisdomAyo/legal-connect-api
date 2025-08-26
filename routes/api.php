<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\OnboardingController;
use App\Enums\Role;

Route::prefix('auth')->group(function () {
    Route::post('/signup/client', [AuthController::class, 'signupClient']);
    Route::post('/signup/lawyer', [AuthController::class, 'signupLawyer']);
    Route::post('/signin', [AuthController::class, 'signin'])->middleware('throttle:5,1');
    Route::post('/verify-email', [AuthController::class, 'verifyUserEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerificationLink']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
       // Social Authentication
    Route::get('/social/{provider}', [AuthController::class, 'gotoProvider'])->middleware('guest');
    Route::get('/social/{provider}/callback', [AuthController::class, 'socialSignIn'])->middleware('guest');
});

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/signout', [AuthController::class, 'signout']);
        // Email Verification
        Route::post('/email/resend-verification', [AuthController::class, 'resendVerificationLink'])->middleware('throttle:6,1');
    });
    Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyUserEmail'])->middleware(['auth:sanctum', 'signed'])->name('verification.verify');

Route::middleware(['auth:sanctum', 'role:' . Role::Lawyer->value])->prefix('lawyer/onboarding')->group(function () {

    // An endpoint to get the current status and data for the onboarding form
        Route::get('/status', [OnboardingController::class, 'getStatus']);

        // Get metadata for all steps
        Route::get('/steps', [OnboardingController::class, 'getStepsMetadata']);

        // Get metadata for specific step
        Route::get('/steps/{step}/metadata', [OnboardingController::class, 'getStepMetadata']);

        // Get validation rules for specific step
        Route::get('/steps/{step}/validation-rules', [OnboardingController::class, 'getStepValidationRules']);

        // Get current data for specific step
        Route::get('/steps/{step}/data', [OnboardingController::class, 'getStepData']);

        // Save data for specific step
        Route::post('/steps/{step}', [OnboardingController::class, 'saveStepData']);

        // Skip specific step
        Route::post('/steps/{step}/skip', [OnboardingController::class, 'skipStep']);

        // Bulk save multiple steps
        Route::post('/bulk-save', [OnboardingController::class, 'bulkSaveSteps']);

        // Submit profile for review
        Route::post('/submit', [OnboardingController::class, 'submitForReview']);


});

        Route::middleware(['auth:sanctum'])->group(function () {
            Route::get('/practice-areas', function () {
                return response()->json(\App\Models\PracticeArea::all());
            });

            Route::get('/specializations', function () {
                return response()->json(\App\Models\Specialization::all());
            });

            Route::get('/languages', function () {
                return response()->json(\App\Models\Language::all());
            });
        });




