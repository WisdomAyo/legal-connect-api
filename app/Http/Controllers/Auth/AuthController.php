<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\AuthService;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\SignupClientRequest;
use App\Http\Requests\Auth\SignupLawyerRequest;
use App\Http\Requests\Auth\SigninRequest;

class AuthController extends Controller
{
    //

  protected AuthService $service;

    public function __construct(protected AuthService $authService)
    {
        $this->service = $authService;
    }

    public function signupClient(SignupClientRequest $request): JsonResponse
    {
         return response()->json($this->service->signup($request->validated(), 'client'));
    }

    public function signupLawyer(SignupLawyerRequest $request): JsonResponse
    {
        return response()->json($this->service->signup($request->validated(), 'lawyer'));
    }

    public function signin(SigninRequest $request): JsonResponse
    {
        return response()->json($this->service->signin($request->validated()));
    }

    public function signout(Request $request): JsonResponse
    {
        $result = $this->service->signout($request->user());
        return response()->json($result);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse

    {
        return $this->service->forgotPassword($request->validated());
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return response()->json($this->service->resetPassword($request->validated()));
    }

    public function verifyUserEmail(Request $request): JsonResponse
    {
        return response()->json($this->service->verifyUserEmail($request));
    }

    public function resendVerificationLink(Request $request): JsonResponse
    {
        return response()->json($this->service->resendVerificationLink($request));
    }

    public function gotoProvider($provider): JsonResponse
    {
        return response()->json($this->service->gotoProvider($provider));
    }

    public function socialSignIn($provider): JsonResponse
    {
        return response()->json($this->service->socialSignIn($provider));
    }
}
