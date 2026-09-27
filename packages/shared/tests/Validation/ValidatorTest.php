<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Tests\Validation;

use PHPUnit\Framework\TestCase;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use ClassificacaoMsgStreams\Shared\Validation\Rules\EmailRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\RequiredRule;
use ClassificacaoMsgStreams\Shared\Validation\Validator;

final class ValidatorTest extends TestCase
{
    public function testPassesWhenAllRulesAreSatisfied(): void
    {
        $this->expectNotToPerformAssertions();

        Validator::validate([
            ['code' => 'email', 'value' => 'user@example.com', 'rules' => [new RequiredRule(), new EmailRule()]],
        ]);
    }

    public function testAccumulatesErrorsFromMultipleFields(): void
    {
        try {
            Validator::validate([
                ['code' => 'name', 'value' => '', 'rules' => [new RequiredRule()]],
                ['code' => 'email', 'value' => 'not-an-email', 'rules' => [new RequiredRule(), new EmailRule()]],
            ]);
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $messages = array_map(static fn ($error) => $error->getMessage(), $exception->getErrors());

            $this->assertSame(['name.required', 'email.invalid.email'], $messages);
        }
    }
}
