<?php

declare(strict_types=1);

namespace App\Shared\Errors;

/**
 * Shape padrão de erro da API. Toda resposta de erro emitida pela aplicação
 * (via ApiExceptionListener) segue esta estrutura, independentemente da
 * origem da exceção (validação, domínio, HTTP ou erro inesperado).
 */
final class ApiErrorResponse
{
    public readonly string $timestamp;

    /**
     * @param mixed $details Detalhes estruturados adicionais (ex.: lista de erros de validação)
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $error,
        public readonly string $message,
        public readonly mixed $details = null,
        public readonly ?string $path = null,
        ?string $timestamp = null,
    ) {
        $this->timestamp = $timestamp ?? (new \DateTimeImmutable())->format(DATE_ATOM);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'statusCode' => $this->statusCode,
            'error' => $this->error,
            'message' => $this->message,
            'details' => $this->details,
            'path' => $this->path,
            'timestamp' => $this->timestamp,
        ];
    }
}
