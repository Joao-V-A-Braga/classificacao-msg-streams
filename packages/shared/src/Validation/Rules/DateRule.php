<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation\Rules;

use ClassificacaoMsgStreams\Shared\Validation\RuleUtils;
use ClassificacaoMsgStreams\Shared\Validation\ValidationRuleInterface;

final class DateRule implements ValidationRuleInterface
{
    public function validate(mixed $value): ?string
    {
        return RuleUtils::validateEachValue(
            $value,
            'invalid.date',
            static fn (mixed $item): bool => $item instanceof \DateTimeInterface,
        );
    }
}
