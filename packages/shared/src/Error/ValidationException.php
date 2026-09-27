<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Error;

class ValidationException extends DomainError
{
    /**
     * @param ValidationError[] $errors
     */
    public function __construct(private readonly array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message, 422);
    }

    /**
     * @return ValidationError[]
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
