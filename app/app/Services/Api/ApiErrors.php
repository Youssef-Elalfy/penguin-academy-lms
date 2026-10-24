<?php

namespace App\Services\Api;

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApiErrors
{
    public static function render(Response $response, Throwable $exception, Request $request): Response
    {
        if (! $request->is('api/*')) {
            return SecurityHeaders::apply($request, $response);
        }

        $status = $response->getStatusCode();
        [$code, $message] = match ($status) {
            400 => ['bad_request', 'Invalid request.'],
            401 => ['unauthenticated', 'Valid credentials or a valid bearer token are required.'],
            403 => ['forbidden', 'You are not authorized to perform this operation.'],
            404 => ['not_found', 'The requested resource was not found.'],
            405 => ['method_not_allowed', 'This HTTP method is not supported.'],
            409 => ['conflict', 'The resource changed. Refresh its current state and try again.'],
            422 => ['validation_failed', 'The given data was invalid.'],
            429 => ['rate_limited', 'Too many requests. Please try again later.'],
            default => ['server_error', 'An unexpected error occurred.'],
        };
        $body = ['message' => $message, 'code' => $code];
        if ($exception instanceof ValidationException) {
            $body['errors'] = $exception->errors();
        }
        $headers = array_intersect_key($response->headers->all(), array_flip(['retry-after', 'allow', 'x-ratelimit-limit', 'x-ratelimit-remaining', 'x-ratelimit-reset']));
        $headers['Cache-Control'] = 'no-store, private';
        $headers['X-Content-Type-Options'] = 'nosniff';

        return SecurityHeaders::apply($request, response()->json($body, $status, $headers));
    }
}
