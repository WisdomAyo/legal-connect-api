<?php

use App\Enums\Role;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ClientDashboardController;
use App\Http\Controllers\LawyerDashboardController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\SummaryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Authentication Routes (Public)
|--------------------------------------------------------------------------
*/

Route::middleware(['role:lawyer'])->get('/test-lawyer', function () {
    return 'You are a lawyer!';
});

Route::prefix('auth')->group(function () {
    Route::post('/signup/client', [AuthController::class, 'signupClient']);
    Route::post('/signup/lawyer', [AuthController::class, 'signupLawyer']);
    Route::post('/signin', [AuthController::class, 'signin'])->middleware('throttle:5,1');
    Route::post('/verify-email', [AuthController::class, 'verifyUserEmail']);
    Route::post('/resend-verification', [AuthController::class, 'resendVerificationLink']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/social/{provider}', [AuthController::class, 'gotoProvider'])->middleware('guest');
    Route::get('/social/{provider}/callback', [AuthController::class, 'socialSignIn'])->middleware('guest');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/signout', [AuthController::class, 'signout']);
    Route::get('/user', [AuthController::class, 'user']);
});

/*
|--------------------------------------------------------------------------
| Lawyer Onboarding Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['role:lawyer'])->prefix('lawyer/onboarding')->group(function () {
    // Status and Metadata
    Route::get('/status', [OnboardingController::class, 'getStatus']);
    Route::get('/steps', [OnboardingController::class, 'getStepsMetadata']);
    Route::get('/steps/{step}/metadata', [OnboardingController::class, 'getStepMetadata']);
    Route::get('/steps/{step}/validation-rules', [OnboardingController::class, 'getStepValidationRules']);
    Route::get('/steps/{step}/data', [OnboardingController::class, 'getStepData']);
    // Save Step Data
    Route::post('/steps/{step}', [OnboardingController::class, 'saveStepData']);
    Route::post('/steps/{step}/skip', [OnboardingController::class, 'skipStep']);
    // Submit for Review
    Route::post('/submit', [OnboardingController::class, 'submitForReview']);

});

/*
|--------------------------------------------------------------------------
| Client Dashboard Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['role:client'])->prefix('client')->group(function () {
    Route::get('/dashboard', [ClientDashboardController::class, 'index']);
    Route::get('/dashboard/stats', [ClientDashboardController::class, 'getStats']);
    Route::get('/lawyers/search', [ClientDashboardController::class, 'searchLawyers']);
    Route::get('/lawyers/{id}', [ClientDashboardController::class, 'viewLawyer']);
    Route::get('/appointments', [ClientDashboardController::class, 'getAppointments']);
    Route::post('/appointments', [ClientDashboardController::class, 'bookAppointment']);
    Route::get('/messages', [ClientDashboardController::class, 'getMessages']);
    Route::post('/messages', [ClientDashboardController::class, 'sendMessage']);
});

/*
|--------------------------------------------------------------------------
| Lawyer Dashboard Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['role:lawyer'])->prefix('lawyer')->group(function () {
    Route::get('/dashboard', [LawyerDashboardController::class, 'index']);
    Route::get('/dashboard/stats', [LawyerDashboardController::class, 'getStats']);
    Route::get('/profile', [LawyerDashboardController::class, 'getProfile']);
    Route::put('/profile', [LawyerDashboardController::class, 'updateProfile']);
    Route::get('/appointments', [LawyerDashboardController::class, 'getAppointments']);
    Route::put('/appointments/{id}', [LawyerDashboardController::class, 'updateAppointment']);
    Route::get('/availability', [LawyerDashboardController::class, 'getAvailability']);
    Route::put('/availability', [LawyerDashboardController::class, 'updateAvailability']);
    Route::get('/clients', [LawyerDashboardController::class, 'getClients']);
    Route::get('/earnings', [LawyerDashboardController::class, 'getEarnings']);
});

// Lawyer-Specific Authenticated Routes
// Route::middleware(['auth:sanctum', 'role:lawyer'])->prefix('lawyer')->name('lawyer.')->group(function () {
//     Route::get('/professional', LawyerDashboardController::class)->name('professional');
// });


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

    Route::post('/summaries/text', [SummaryController::class, 'summarizeText'])->name('summaries.text');
});



Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
