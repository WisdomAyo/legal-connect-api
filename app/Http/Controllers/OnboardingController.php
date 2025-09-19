<?php

namespace App\Http\Controllers;

use App\Services\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    public function __construct(
        private OnboardingService $onboardingService
    ) {}

    public function getStatus(Request $request): JsonResponse
    {
        $result = $this->onboardingService->getStatus($request->user());

        return response()->json($result);
    }

    public function getStepsMetadata(Request $request): JsonResponse
    {
        $result = $this->onboardingService->getStepsMetadata();

        return response()->json($result);
    }

    public function getStepMetadata(string $step): JsonResponse
    {
        $result = $this->onboardingService->getStepMetadata($step);

        return response()->json($result);
    }

    public function getStepValidationRules(string $step): JsonResponse
    {
        $result = $this->onboardingService->getStepValidationRules($step);

        return response()->json($result);
    }

    public function getStepData(Request $request, string $step): JsonResponse
    {
        $result = $this->onboardingService->getStepData($request->user(), $step);

        return response()->json($result);
    }

    public function saveStepData(Request $request, string $step): JsonResponse
    {
        // Dynamic validation based on step
        $rules = $this->onboardingService->getStepValidationRules($step)['data'];
        $validated = $request->validate($rules);

        $data = array_merge($validated, $request->allFiles());

        $result = $this->onboardingService->saveStepData(
            $request->user(),
            $step,
            $data
        );

        return response()->json($result);
    }

    public function skipStep(Request $request, string $step): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $result = $this->onboardingService->skipStep(
            $request->user(),
            $step,
            $validated['reason'] ?? null
        );

        return response()->json($result);
    }

    public function submitForReview(Request $request): JsonResponse
    {
        $result = $this->onboardingService->submitForReview($request->user());

        return response()->json($result);
    }
}
