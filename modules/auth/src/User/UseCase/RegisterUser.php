<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\User\UseCase;

use ClassificacaoMsgStreams\Auth\User\Model\User;
use ClassificacaoMsgStreams\Auth\User\Provider\CryptoProviderInterface;
use ClassificacaoMsgStreams\Auth\User\Provider\UserRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class RegisterUserIn
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
    ) {
    }
}

final class RegisterUserOut
{
    public function __construct(
        public readonly User $user,
    ) {
    }
}

/**
 * @template-implements UseCaseInterface<RegisterUserIn, RegisterUserOut>
 */
final class RegisterUser implements UseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly CryptoProviderInterface $cryptoProvider,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var RegisterUserIn $input */
        if ($this->userRepository->findByEmail($input->email) !== null) {
            throw new ConflictError('user.email.already_used');
        }

        $hashedPassword = $this->cryptoProvider->hash($input->password);

        $user = new User([
            'name' => $input->name,
            'email' => $input->email,
            'password' => $hashedPassword,
        ]);
        $user->validate();

        $this->userRepository->create($user);

        return new RegisterUserOut($user);
    }
}
