<?php

namespace App\Exceptions\Handlers;

use App\Enums\ApiErrorCode;
use App\Exceptions\ApiException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiExceptionRenderer
{
    public function renderException(Throwable $exception, Request $request): ?JsonResponse
    {
        if (! $request->is('api/v1', 'api/v1/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof ApiException => $this->render($exception),

            $exception instanceof UniqueConstraintViolationException => $this->renderUniqueViolation($exception) ?? $this->renderHttpError(500),

            $exception instanceof ValidationException => $this->renderHttpError(422, details: $exception->errors()),

            $exception instanceof AuthenticationException => $this->renderHttpError(401),

            $exception instanceof AuthorizationException => $this->renderHttpError(403),

            $exception instanceof ModelNotFoundException => $this->renderHttpError(404),

            $exception instanceof HttpExceptionInterface => $this->renderHttpError(
                $exception->getStatusCode(),
                $exception->getHeaders(),
            ),

            default => $this->renderHttpError(500),
        };
    }

    private function render(ApiException $exception): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $exception->errorCode->value,
                'message' => $exception->getMessage(),
                'details' => $exception->details ?: new stdClass,
            ],
        ], $exception->httpStatus);
    }

    private function renderUniqueViolation(UniqueConstraintViolationException $exception): ?JsonResponse
    {
        if (
            ($exception->errorInfo[0] ?? null) !== '23505'
            || ! str_contains($exception->getMessage(), 'users_email_unique')
        ) {
            return null;
        }

        return $this->render(new ApiException(
            errorCode: ApiErrorCode::EmailAlreadyExists,
            message: 'Email already exists',
            httpStatus: 409,
        ));
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $details
     */
    private function renderHttpError(int $status, array $headers = [], array $details = []): JsonResponse
    {
        $message = $status >= 500
            ? 'Internal Server Error'
            : (Response::$statusTexts[$status] ?? 'HTTP Error');

        return response()->json([
            'error' => [
                'code' => $status,
                'message' => $message,
                'details' => $details ?: new stdClass,
            ],
        ], $status, $headers);
    }
}
