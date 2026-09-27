<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation\Rules;

use ClassificacaoMsgStreams\Shared\Validation\RuleUtils;
use ClassificacaoMsgStreams\Shared\Validation\ValidationRuleInterface;

final class PersonNameRule implements ValidationRuleInterface
{
    private const PART = "[\\p{L}\\p{M}]+(?:['-][\\p{L}\\p{M}]+)*";

    public function validate(mixed $value): ?string
    {
        return RuleUtils::validateEachValue($value, 'person.name', function (mixed $item): bool {
            if (!is_string($item)) {
                return false;
            }

            $pattern = '/^\s*' . self::PART . '(?:\s+' . self::PART . ')+\s*$/u';

            return preg_match($pattern, $item) === 1;
        });
    }
}
