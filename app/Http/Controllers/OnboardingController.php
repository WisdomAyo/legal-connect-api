<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Exceptions\OnboardingException;

class OnboardingController extends Controller
{
    public function __construct(protected OnboardingService $onboardingService)
    {
    }

    /**
     * Get comprehensive onboarding status
     */
    public function getStatus(Request $request): JsonResponse
    {
        try {
            return $this->onboardingService->getStatus($request->user());
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch onboarding status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get metadata for all onboarding steps
     */
    public function getStepsMetadata(): JsonResponse
    {
        try {
            $metadata = $this->onboardingService->getAllStepsMetadata();

            return response()->json([
                'message' => 'Steps metadata retrieved successfully',
                'data' => $metadata
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch steps metadata',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get metadata for a specific step
     */
    public function getStepMetadata(int $step): JsonResponse
    {
        try {
            $metadata = $this->onboardingService->getStepMetadata($step);

            return response()->json([
                'message' => 'Step metadata retrieved successfully',
                'data' => $metadata
            ]);
        } catch (OnboardingException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch step metadata',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save data for a specific onboarding step
     */
    public function saveStepData(Request $request, int $step): JsonResponse
    {
        try {
            // Get validation rules for this step
            $rules = $this->onboardingService->getStepValidationRules($step);

            // Validate the request
            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            return $this->onboardingService->saveStepData(
                $request->user(),
                $step,
                $validator->validated()
            );

        } catch (OnboardingException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to save step data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current data for a specific step
     */
    public function getStepData(Request $request, int $step): JsonResponse
    {
        try {
            if (!isset($this->onboardingService->getAllStepsMetadata()[$step - 1])) {
                throw new OnboardingException("Invalid onboarding step: {$step}");
            }
        
            $stepHandler = app($this->onboardingService->steps[$step]);
            $data = $stepHandler->getData($request->user());
            $metadata = $stepHandler->getMetadata();

            return response()->json([
                'message' => 'Step data retrieved successfully',
                'data' => [
                    'step_number' => $step,
                    'metadata' => $metadata,
                    'current_data' => $data,
                    'is_complete' => $stepHandler->isComplete($request->user()),
                    'completion_percentage' => $stepHandler->getCompletionPercentage($request->user()),
                ]
            ]);

        } catch (OnboardingException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch step data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Skip a specific onboarding step
     */
    public function skipStep(Request $request, int $step): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            return $this->onboardingService->skipStep(
                $request->user(),
                $step,
                $validator->validated()['reason'] ?? null
            );

        } catch (OnboardingException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to skip step',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit profile for review
     */
    public function submitForReview(Request $request): JsonResponse
    {
        try {
            return $this->onboardingService->submitForReview($request->user());
        } catch (OnboardingException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to submit profile for review',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get validation rules for a specific step (useful for frontend)
     */
    public function getStepValidationRules(int $step): JsonResponse
    {
        try {
            $rules = $this->onboardingService->getStepValidationRules($step);

            return response()->json([
                'message' => 'Validation rules retrieved successfully',
                'data' => [
                    'step_number' => $step,
                    'validation_rules' => $rules
                ]
            ]);

        } catch (OnboardingException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to fetch validation rules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk save data for multiple steps (useful for saving draft data)
     */
    public function bulkSaveSteps(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'steps' => 'required|array',
                'steps.*.step_number' => 'required|integer|min:1|max:5',
                'steps.*.data' => 'required|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $results = [];
            $user = $request->user();

            foreach ($validator->validated()['steps'] as $stepData) {
                $step = $stepData['step_number'];
                $data = $stepData['data'];

                try {
                    // Validate step data
                    $stepRules = $this->onboardingService->getStepValidationRules($step);
                    $stepValidator = Validator::make($data, $stepRules);

                    if ($stepValidator->fails()) {
                        $results[$step] = [
                            'success' => false,
                            'errors' => $stepValidator->errors()
                        ];
                        continue;
                    }

                    // Save step data
                    $this->onboardingService->saveStepData($user, $step, $stepValidator->validated());
                    $results[$step] = ['success' => true];

                } catch (\Exception $e) {
                    $results[$step] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }

            $successCount = collect($results)->where('success', true)->count();
            $totalCount = count($results);

            return response()->json([
                'message' => "Bulk save completed: {$successCount}/{$totalCount} steps saved successfully",
                'data' => [
                    'results' => $results,
                    'summary' => [
                        'total_steps' => $totalCount,
                        'successful_steps' => $successCount,
                        'failed_steps' => $totalCount - $successCount,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Bulk save failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
