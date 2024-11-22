<?php

declare(strict_types=1);

namespace Antogkou\LaravelSalesforce\Exceptions;

use Exception;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SalesforceException extends Exception implements Responsable
{
    private const DEFAULT_STATUS_CODE = Response::HTTP_INTERNAL_SERVER_ERROR;

    /**
     * @param  array<string, mixed>  $context  Additional context information about the exception
     */
    public function __construct(
        string $message,
        int $code = self::DEFAULT_STATUS_CODE,
        ?Throwable $previous = null,
        private readonly array $context = []
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function toResponse($request): JsonResponse
    {
        $statusCode = $this->isValidHttpStatusCode($this->getCode())
            ? $this->getCode()
            : self::DEFAULT_STATUS_CODE;

        return new JsonResponse([
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'context' => $this->context,
        ], $statusCode);
    }

    /**
     * Get the additional context information about the exception.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    private function isValidHttpStatusCode(int $code): bool
    {
        return $code >= 100 && $code < 600;
    }
}
