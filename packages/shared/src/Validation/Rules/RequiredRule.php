<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation\Rules;

use ClassificacaoMsgStreams\Shared\Validation\RuleUtils;
use ClassificacaoMsgStreams\Shared\Validation\ValidationRuleInterface;

final class RequiredRule implements ValidationRuleInterface
{
    public function validate(mixed $value): ?string
    {
        return RuleUtils::isEmptyValue($value) ? 'required' : null;
    }
}
