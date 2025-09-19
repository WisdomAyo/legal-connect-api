<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookAppointmentRequest;
use App\Http\Requests\GetAppointmentsRequest;
use App\Http\Requests\GetMessagesRequest;
use App\Http\Requests\SearchLawyersRequest;
use App\Http\Requests\SendMessageRequest;
use App\Services\AppointmentService;
use App\Services\ClientDashboardService;
use App\Services\LawyerSearchService;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientDashboardController extends Controller
{
    public function __construct(
        private ClientDashboardService $dashboardService,
        private LawyerSearchService $searchService,
        private AppointmentService $appointmentService,
        private MessageService $messageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->dashboardService->getDashboardOverview($request->user());

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function getStats(Request $request): JsonResponse
    {
        $stats = $this->dashboardService->getClientStats($request->user());

        return response()->json(['success' => true, 'data' => $stats]);
    }

    public function searchLawyers(SearchLawyersRequest $request): JsonResponse
    {
        $results = $this->searchService->searchLawyers($request->validated(), $request->user());

        return response()->json(['success' => true, 'data' => $results]);
    }

    public function viewLawyer(Request $request, int $id): JsonResponse
    {
        $lawyer = $this->searchService->getLawyerDetails($id);
        $this->dashboardService->trackProfileView($request->user(), $id);

        return response()->json(['success' => true, 'data' => $lawyer]);
    }

    public function getAppointments(GetAppointmentsRequest $request): JsonResponse
    {
        $appointments = $this->appointmentService->getClientAppointments($request->user(), $request->validated());

        return response()->json(['success' => true, 'data' => $appointments]);
    }

    public function bookAppointment(BookAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->appointmentService->bookAppointment($request->user(), $request->validated());

        return response()->json(['success' => true, 'message' => 'Appointment request sent successfully', 'data' => $appointment], 201);
    }

    public function getMessages(GetMessagesRequest $request): JsonResponse
    {
        $messages = $this->messageService->getClientMessages($request->user(), $request->validated());

        return response()->json(['success' => true, 'data' => $messages]);
    }

    public function sendMessage(SendMessageRequest $request): JsonResponse
    {
        $message = $this->messageService->sendMessage($request->user(), $request->validated());

        return response()->json(['success' => true, 'message' => 'Message sent successfully', 'data' => $message], 201);
    }
}
