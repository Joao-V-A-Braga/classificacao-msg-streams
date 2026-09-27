<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Error;

class UnauthorizedError extends DomainError
{
    public function __construct(string $message)
    {
        parent::__construct($message, 401);
    }
}
