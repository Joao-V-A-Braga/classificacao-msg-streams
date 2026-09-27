<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation;

final class RuleUtils
{
    public static function isEmptyValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return count($value) === 0;
        }

        return false;
    }

    public static function getValueLength(mixed $value): ?int
    {
        if (is_string($value)) {
            return mb_strlen($value);
        }

        if (is_array($value)) {
            return count($value);
        }

        return null;
    }

    /**
     * @param callable(mixed): bool $predicate
     */
    public static function validateEachValue(mixed $value, string $errorCode, callable $predicate): ?string
    {
        if (self::isEmptyValue($value)) {
            return null;
        }

        $items = is_array($value) ? $value : [$value];

        foreach ($items as $item) {
            if (!$predicate($item)) {
                return $errorCode;
            }
        }

        return null;
    }
}
