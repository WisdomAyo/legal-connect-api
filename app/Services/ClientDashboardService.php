<?php

namespace App\Services;

use App\Http\Resources\Client\AppointmentSummaryResource;
use App\Http\Resources\Client\CaseSummaryResource;
use App\Http\Resources\LawyerRecommendationResource;
use App\Models\User;
use App\Repositories\AppointmentRepository;
use App\Repositories\CaseRepository;
use App\Repositories\LawyerRepository;

class ClientDashboardService
{
    // We assume these repositories exist and have the necessary methods.
    public function __construct(
        protected CaseRepository $caseRepository,
        protected AppointmentRepository $appointmentRepository,
        protected LawyerRepository $lawyerRepository
    ) {}

    /**
     * Gathers AND formats all data for the client's dashboard overview.
     */
    public function getDataForDashboard(User $client): array
    {
        $stats = [
            'active_cases' => $this->caseRepository->getActiveCaseCount($client),
            'completed_cases' => $this->caseRepository->getCompletedCaseCount($client),
            'total_lawyers' => $this->caseRepository->getAssociatedLawyerCount($client),
            'monthly_spend' => '$0.00', // This can be replaced with a real calculation
        ];

        $recentCases = $this->caseRepository->getRecentCasesForClient($client, 3);
        $upcomingAppointments = $this->appointmentRepository->getUpcomingAppointmentsForClient($client, 2);
        $recommendedLawyers = $this->lawyerRepository->getRecommendationsForClient($client, 2);

        // Assemble the final, transformed data structure right here in the service.
        return [
            'stats' => $stats,
            'recentCases' => CaseSummaryResource::collection($recentCases),
            'upcomingAppointments' => AppointmentSummaryResource::collection($upcomingAppointments),
            'recommendedLawyers' => LawyerRecommendationResource::collection($recommendedLawyers),
        ];
    }
}
