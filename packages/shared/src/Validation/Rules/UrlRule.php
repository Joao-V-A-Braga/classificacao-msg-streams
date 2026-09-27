<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation\Rules;

use ClassificacaoMsgStreams\Shared\Validation\RuleUtils;
use ClassificacaoMsgStreams\Shared\Validation\ValidationRuleInterface;

/**
 * Absolute http(s) URL with a host.
 */
final class UrlRule implements ValidationRuleInterface
{
    public function validate(mixed $value): ?string
    {
        if (RuleUtils::isEmptyValue($value)) {
            return null;
        }

        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return 'invalid.url';
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        $host = parse_url($value, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true) && is_string($host) && $host !== '' ? null : 'invalid.url';
    }
}
