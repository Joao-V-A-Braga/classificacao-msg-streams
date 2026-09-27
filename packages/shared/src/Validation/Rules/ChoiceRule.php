<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation\Rules;

use ClassificacaoMsgStreams\Shared\Validation\RuleUtils;
use ClassificacaoMsgStreams\Shared\Validation\ValidationRuleInterface;

final class ChoiceRule implements ValidationRuleInterface
{
    /**
     * @param array<int, mixed> $allowed
     */
    public function __construct(private readonly array $allowed)
    {
    }

    public function validate(mixed $value): ?string
    {
        if (RuleUtils::isEmptyValue($value)) {
            return null;
        }

        return in_array($value, $this->allowed, strict: true) ? null : 'invalid.choice';
    }
}
