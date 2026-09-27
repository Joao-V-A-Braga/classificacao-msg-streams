<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Error;

class ConflictError extends DomainError
{
    public function __construct(string $message)
    {
        parent::__construct($message, 409);
    }
}
