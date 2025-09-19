<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetAppointmentsRequest;
use App\Http\Requests\GetStatsRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Services\AppointmentService;
use App\Services\EarningsService;
use App\Services\LawyerDashboardService;
// Import all the necessary Form Requests
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LawyerDashboardController extends Controller
{
    public function __construct(
        private LawyerDashboardService $dashboardService,
        private AppointmentService $appointmentService,
        private ProfileService $profileService,
        private EarningsService $earningsService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getDashboardOverview($request->user());

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getStats(GetStatsRequest $request): JsonResponse
    {
        $stats = $this->dashboardService->getLawyerStats($request->user(), $request->validated());

        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function getProfile(Request $request): JsonResponse
    {
        $profile = $this->profileService->getLawyerProfile($request->user());

        return response()->json(['success' => true, 'data' => $profile]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $profile = $this->profileService->updateLawyerProfile($request->user(), $request->validated());

        return response()->json(['success' => true, 'message' => 'Profile updated successfully', 'data' => $profile]);
    }

    public function getAppointments(GetAppointmentsRequest $request): JsonResponse
    {
        $appointments = $this->appointmentService->getLawyerAppointments($request->user(), $request->validated());

        return response()->json(['success' => true, 'data' => $appointments]);
    }

    public function updateAppointment(UpdateAppointmentRequest $request, int $id): JsonResponse
    {
        $appointment = $this->appointmentService->updateAppointment($request->user(), $id, $request->validated());

        return response()->json(['success' => true, 'message' => 'Appointment updated successfully', 'data' => $appointment]);
    }

    public function getAvailability(Request $request): JsonResponse
    {
        $availability = $this->profileService->getAvailability($request->user());

        return response()->json(['success' => true, 'data' => $availability]);
    }

    public function updateAvailability(UpdateAvailabilityRequest $request): JsonResponse
    {
        $availability = $this->profileService->updateAvailability($request->user(), $request->validated());

        return response()->json(['success' => true, 'message' => 'Availability updated successfully', 'data' => $availability]);
    }
}
