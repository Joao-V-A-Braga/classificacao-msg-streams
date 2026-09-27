<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\User\UseCase;

use ClassificacaoMsgStreams\Auth\User\Model\User;
use ClassificacaoMsgStreams\Auth\User\Provider\CryptoProviderInterface;
use ClassificacaoMsgStreams\Auth\User\Provider\JwtProviderInterface;
use ClassificacaoMsgStreams\Auth\User\Provider\UserRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Error\UnauthorizedError;
use ClassificacaoMsgStreams\Shared\UseCase\UseCaseInterface;

final class LoginUserIn
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }
}

final class LoginUserOut
{
    public function __construct(
        public readonly string $token,
        public readonly User $user,
    ) {
    }
}

/**
 * @template-implements UseCaseInterface<LoginUserIn, LoginUserOut>
 */
final class LoginUser implements UseCaseInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly CryptoProviderInterface $cryptoProvider,
        private readonly JwtProviderInterface $jwtProvider,
    ) {
    }

    public function execute(mixed $input): mixed
    {
        /** @var LoginUserIn $input */
        $user = $this->userRepository->findByEmail($input->email);
        if ($user === null) {
            throw new UnauthorizedError('auth.invalid_credentials');
        }

        if (!$this->cryptoProvider->verify($input->password, $user->getPassword())) {
            throw new UnauthorizedError('auth.invalid_credentials');
        }

        $token = $this->jwtProvider->generate([
            'sub' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        return new LoginUserOut($token, $user);
    }
}
