<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Validation;

use ClassificacaoMsgStreams\Shared\Error\ValidationError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;

final class Validator
{
    /**
     * @param array<int, array{code: string, value: mixed, rules: ValidationRuleInterface[]}> $fields
     */
    public static function validate(array $fields): void
    {
        $errors = [];

        foreach ($fields as $field) {
            foreach ($field['rules'] as $rule) {
                $errorCode = $rule->validate($field['value']);

                if ($errorCode !== null) {
                    $errors[] = new ValidationError("{$field['code']}.{$errorCode}");
                }
            }
        }

        if (count($errors) > 0) {
            throw new ValidationException($errors);
        }
    }
}
