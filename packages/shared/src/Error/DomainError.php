<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Error;

class DomainError extends \RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 500)
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
