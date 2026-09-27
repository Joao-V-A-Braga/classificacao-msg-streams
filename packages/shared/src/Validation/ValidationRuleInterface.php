<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation;

interface ValidationRuleInterface
{
    /**
     * Returns null when the value is valid, or a short error code (dot-separated,
     * e.g. "min.length") describing the failure.
     */
    public function validate(mixed $value): ?string;
}
