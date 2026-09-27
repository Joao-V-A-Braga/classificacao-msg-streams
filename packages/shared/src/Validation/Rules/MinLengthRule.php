<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation\Rules;

use ClassificacaoMsgStreams\Shared\Validation\RuleUtils;
use ClassificacaoMsgStreams\Shared\Validation\ValidationRuleInterface;

final class MinLengthRule implements ValidationRuleInterface
{
    public function __construct(private readonly int $min)
    {
    }

    public function validate(mixed $value): ?string
    {
        if (RuleUtils::isEmptyValue($value)) {
            return null;
        }

        $length = RuleUtils::getValueLength($value);

        return $length !== null && $length >= $this->min ? null : 'min.length';
    }
}
