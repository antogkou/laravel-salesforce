<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class SalesforceException extends Exception implements Responsable
{
  private const int DEFAULT_STATUS_CODE = Response::HTTP_INTERNAL_SERVER_ERROR;

  private array $context;

  public function __construct(string $message, int $code = self::DEFAULT_STATUS_CODE, ?Throwable $previous = null, array $context = [])
  {
    parent::__construct($message, $code, $previous);
    $this->context = $context;
  }

  public function toResponse($request): JsonResponse
  {
    $statusCode = $this->isValidHttpStatusCode($this->getCode())
      ? $this->getCode()
      : self::DEFAULT_STATUS_CODE;

    return new JsonResponse([
      'message' => $this->getMessage(),
      'code' => $this->getCode(),
      'context' => $this->getContext(),
    ], $statusCode);
  }

  private function isValidHttpStatusCode(int $code): bool
  {
    return $code >= 100 && $code < 600;
  }

  public function getContext(): array
  {
    return $this->context;
  }
}
