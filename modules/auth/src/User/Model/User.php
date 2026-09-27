<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\User\Model;

use ClassificacaoMsgStreams\Shared\Model\AbstractEntity;
use ClassificacaoMsgStreams\Shared\Validation\Rules\EmailRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\MaxLengthRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\PasswordHashRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\PersonNameRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\RequiredRule;
use ClassificacaoMsgStreams\Shared\Validation\Validator;

final class User extends AbstractEntity
{
    public function getName(): string
    {
        return $this->props['name'];
    }

    public function getEmail(): string
    {
        return $this->props['email'];
    }

    public function getPassword(): string
    {
        return $this->props['password'];
    }

    public function validate(): void
    {
        Validator::validate([
            [
                'code' => 'user.name',
                'value' => $this->getName(),
                'rules' => [new RequiredRule(), new PersonNameRule(), new MaxLengthRule(255)],
            ],
            [
                'code' => 'user.email',
                'value' => $this->getEmail(),
                'rules' => [new RequiredRule(), new EmailRule(), new MaxLengthRule(255)],
            ],
            [
                'code' => 'user.password',
                'value' => $this->getPassword(),
                'rules' => [new RequiredRule(), new PasswordHashRule()],
            ],
        ]);
    }
}
