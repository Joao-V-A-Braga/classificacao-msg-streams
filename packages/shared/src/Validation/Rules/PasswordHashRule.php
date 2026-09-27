<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation\Rules;

use ClassificacaoMsgStreams\Shared\Validation\RuleUtils;
use ClassificacaoMsgStreams\Shared\Validation\ValidationRuleInterface;

/**
 * Validates that a field already holds a hashed password produced by
 * PHP's native password_hash() (bcrypt/argon2 formats), never a plain
 * password. Equivalent to BcryptHashRule in the Node catalog.
 */
final class PasswordHashRule implements ValidationRuleInterface
{
    private const PATTERN = '/^\$(2[abxy]|argon2i(d)?)\$/';

    public function validate(mixed $value): ?string
    {
        return RuleUtils::validateEachValue(
            $value,
            'invalid.password_hash',
            static fn (mixed $item): bool => is_string($item) && preg_match(self::PATTERN, $item) === 1,
        );
    }
}
