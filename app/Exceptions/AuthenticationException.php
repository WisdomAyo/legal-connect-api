<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticationException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $message  The error message.
     * @param  int  $code  The HTTP status code.
     */
    public function __construct(string $message = 'Unauthenticated.', int $code = 401, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Render the exception into a structured JSON HTTP response.
     *
     * This method is automatically called by Laravel's exception handler.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Authentication Failed', // A generic, safe top-level message
            'error' => $this->getMessage(),      // The specific message (e.g., "The provided credentials do not match...")
        ], $this->getCode()); // Returns the 401 status code
    }
}
