<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation\Rules;

use ClassificacaoMsgStreams\Shared\Validation\RuleUtils;
use ClassificacaoMsgStreams\Shared\Validation\ValidationRuleInterface;

final class EmailRule implements ValidationRuleInterface
{
    private const PATTERN = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';

    public function validate(mixed $value): ?string
    {
        if (RuleUtils::isEmptyValue($value)) {
            return null;
        }

        if (!is_string($value)) {
            return 'invalid.email';
        }

        return preg_match(self::PATTERN, trim($value)) === 1 ? null : 'invalid.email';
    }
}
