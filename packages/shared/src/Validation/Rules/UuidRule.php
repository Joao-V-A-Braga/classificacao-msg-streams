<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation\Rules;

use ClassificacaoMsgStreams\Shared\Validation\RuleUtils;
use ClassificacaoMsgStreams\Shared\Validation\ValidationRuleInterface;

final class UuidRule implements ValidationRuleInterface
{
    private const PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function validate(mixed $value): ?string
    {
        return RuleUtils::validateEachValue(
            $value,
            'invalid.uuid',
            fn (mixed $item): bool => is_string($item) && preg_match(self::PATTERN, $item) === 1,
        );
    }
}
