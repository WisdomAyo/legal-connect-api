<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResendCodeRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\SigninRequest;
use App\Http\Requests\Auth\SignupClientRequest;
use App\Http\Requests\Auth\SignupLawyerRequest;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        return response()->json($this->service->forgotPassword($request->validated()));
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return response()->json($this->service->resetPassword($request->validated()));
    }

    public function verifyUserEmail(VerifyEmailRequest $request): JsonResponse
    {
        return response()->json($this->service->verifyUserEmail($request->validated()));
    }

    public function resendVerificationLink(ResendCodeRequest $request): JsonResponse
    {
        $result = $this->service->resendVerificationLink($request->validated());

        return response()->json($result);
    }

    public function gotoProvider($provider): JsonResponse
    {
        $result = $this->service->gotoProvider($provider);
        $status = $result['success'] ? 200 : 400;
        return response()->json($result, $status);
    }

    public function socialSignIn($provider): JsonResponse
    {
        return response()->json($this->service->socialSignIn($provider));
    }
}
