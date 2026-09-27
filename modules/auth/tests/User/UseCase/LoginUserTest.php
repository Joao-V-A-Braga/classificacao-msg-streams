<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\Tests\User\UseCase;

use ClassificacaoMsgStreams\Auth\Tests\Mock\FakeCryptoProvider;
use ClassificacaoMsgStreams\Auth\Tests\Mock\FakeJwtProvider;
use ClassificacaoMsgStreams\Auth\Tests\Mock\FakeUserRepository;
use ClassificacaoMsgStreams\Auth\User\Model\User;
use ClassificacaoMsgStreams\Auth\User\Provider\CryptoProviderInterface;
use ClassificacaoMsgStreams\Auth\User\Provider\JwtProviderInterface;
use ClassificacaoMsgStreams\Auth\User\UseCase\LoginUser;
use ClassificacaoMsgStreams\Auth\User\UseCase\LoginUserIn;
use ClassificacaoMsgStreams\Auth\User\UseCase\LoginUserOut;
use ClassificacaoMsgStreams\Shared\Error\UnauthorizedError;
use PHPUnit\Framework\TestCase;

final class LoginUserTest extends TestCase
{
    private function persistUser(FakeUserRepository $userRepository, FakeCryptoProvider $cryptoProvider, string $email, string $plainPassword): User
    {
        $user = new User([
            'name' => 'John Doe',
            'email' => $email,
            'password' => $cryptoProvider->hash($plainPassword),
        ]);
        $user->validate();

        return $userRepository->create($user);
    }

    public function testLogsInAndReturnsATokenGeneratedWithTheCorrectClaims(): void
    {
        $userRepository = new FakeUserRepository();
        $cryptoProvider = new FakeCryptoProvider();
        $jwtProvider = new FakeJwtProvider();
        $useCase = new LoginUser($userRepository, $cryptoProvider, $jwtProvider);

        $email = 'john.doe@example.com';
        $password = 'Secret123!';
        $persisted = $this->persistUser($userRepository, $cryptoProvider, $email, $password);

        $input = new LoginUserIn(email: $email, password: $password);
        $result = $useCase->execute($input);

        self::assertInstanceOf(LoginUserOut::class, $result);
        self::assertSame($persisted->getId(), $result->user->getId());

        self::assertSame(1, $jwtProvider->generateCalls);
        self::assertSame(
            ['sub' => $persisted->getId(), 'email' => $email],
            $jwtProvider->lastClaims,
        );
        self::assertSame('fake-token.' . base64_encode((string) json_encode($jwtProvider->lastClaims)), $result->token);
    }

    public function testThrowsUnauthorizedErrorWithInvalidCredentialsWhenEmailDoesNotExist(): void
    {
        $userRepository = new FakeUserRepository();
        $cryptoProvider = new FakeCryptoProvider();
        $jwtProvider = new FakeJwtProvider();
        $useCase = new LoginUser($userRepository, $cryptoProvider, $jwtProvider);

        $input = new LoginUserIn(email: 'missing@example.com', password: 'Secret123!');

        try {
            $useCase->execute($input);
            self::fail('Expected UnauthorizedError to be thrown.');
        } catch (UnauthorizedError $exception) {
            self::assertSame('auth.invalid_credentials', $exception->getMessage());
        }

        self::assertSame(0, $jwtProvider->generateCalls);
    }

    public function testThrowsTheSameUnauthorizedErrorWhenPasswordIsWrong(): void
    {
        $userRepository = new FakeUserRepository();
        $cryptoProvider = new FakeCryptoProvider();
        $jwtProvider = new FakeJwtProvider();
        $useCase = new LoginUser($userRepository, $cryptoProvider, $jwtProvider);

        $email = 'john.doe@example.com';
        $this->persistUser($userRepository, $cryptoProvider, $email, 'Secret123!');

        $input = new LoginUserIn(email: $email, password: 'WrongPassword!');

        try {
            $useCase->execute($input);
            self::fail('Expected UnauthorizedError to be thrown.');
        } catch (UnauthorizedError $exception) {
            // Must be the exact same error code as the "email not found" case,
            // so a caller can never tell which one actually failed.
            self::assertSame('auth.invalid_credentials', $exception->getMessage());
        }

        self::assertSame(0, $jwtProvider->generateCalls);
    }

    public function testCallsJwtProviderGenerateOnlyAfterCryptoProviderVerifySucceeds(): void
    {
        $callOrder = [];

        $userRepository = new FakeUserRepository();
        $email = 'john.doe@example.com';
        $password = 'Secret123!';

        $cryptoProvider = new class ($callOrder) implements CryptoProviderInterface {
            private FakeCryptoProvider $delegate;

            /** @param array<int, string> $callOrder */
            public function __construct(private array &$callOrder)
            {
                $this->delegate = new FakeCryptoProvider();
            }

            public function hash(string $plainPassword): string
            {
                return $this->delegate->hash($plainPassword);
            }

            public function verify(string $plainPassword, string $hashedPassword): bool
            {
                $this->callOrder[] = 'verify';

                return $this->delegate->verify($plainPassword, $hashedPassword);
            }
        };

        $jwtProvider = new class ($callOrder) implements JwtProviderInterface {
            private FakeJwtProvider $delegate;

            /** @param array<int, string> $callOrder */
            public function __construct(private array &$callOrder)
            {
                $this->delegate = new FakeJwtProvider();
            }

            public function generate(array $claims): string
            {
                $this->callOrder[] = 'generate';

                return $this->delegate->generate($claims);
            }

            public function verify(string $token): array
            {
                return $this->delegate->verify($token);
            }
        };

        $this->persistUser($userRepository, new FakeCryptoProvider(), $email, $password);

        $useCase = new LoginUser($userRepository, $cryptoProvider, $jwtProvider);
        $useCase->execute(new LoginUserIn(email: $email, password: $password));

        self::assertSame(['verify', 'generate'], $callOrder);
    }
}
