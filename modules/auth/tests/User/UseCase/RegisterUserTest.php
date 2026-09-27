<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\Tests\User\UseCase;

use ClassificacaoMsgStreams\Auth\Tests\Mock\FakeCryptoProvider;
use ClassificacaoMsgStreams\Auth\Tests\Mock\FakeUserRepository;
use ClassificacaoMsgStreams\Auth\User\Model\User;
use ClassificacaoMsgStreams\Auth\User\Provider\CryptoProviderInterface;
use ClassificacaoMsgStreams\Auth\User\Provider\UserRepositoryInterface;
use ClassificacaoMsgStreams\Auth\User\UseCase\RegisterUser;
use ClassificacaoMsgStreams\Auth\User\UseCase\RegisterUserIn;
use ClassificacaoMsgStreams\Auth\User\UseCase\RegisterUserOut;
use ClassificacaoMsgStreams\Shared\Error\ConflictError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;

final class RegisterUserTest extends TestCase
{
    private function validIn(): RegisterUserIn
    {
        return new RegisterUserIn(
            name: 'John Doe',
            email: 'john.doe@example.com',
            password: 'Secret123!',
        );
    }

    public function testRegistersAUserAndReturnsItWithoutExposingThePlainPassword(): void
    {
        $userRepository = new FakeUserRepository();
        $cryptoProvider = new FakeCryptoProvider();
        $useCase = new RegisterUser($userRepository, $cryptoProvider);

        $input = $this->validIn();
        $result = $useCase->execute($input);

        self::assertInstanceOf(RegisterUserOut::class, $result);
        self::assertInstanceOf(User::class, $result->user);
        self::assertSame($input->name, $result->user->getName());
        self::assertSame($input->email, $result->user->getEmail());

        // The plaintext password must never appear anywhere in the result: only the hash.
        self::assertNotSame($input->password, $result->user->getPassword());
        self::assertStringNotContainsString($input->password, $result->user->getPassword());
        self::assertTrue(password_verify($input->password, $result->user->getPassword()));

        // The persisted entity (as seen through the repository) must match the same guarantees.
        $persisted = $userRepository->findByEmail($input->email);
        self::assertNotNull($persisted);
        self::assertSame($result->user->getId(), $persisted->getId());
        self::assertNotSame($input->password, $persisted->getPassword());
        self::assertStringNotContainsString($input->password, $persisted->getPassword());
    }

    public function testThrowsConflictErrorWhenEmailIsAlreadyUsedAndNeverHashesOrPersists(): void
    {
        $userRepository = new FakeUserRepository();
        $cryptoProvider = new class implements CryptoProviderInterface {
            public int $hashCalls = 0;

            private FakeCryptoProvider $delegate;

            public function __construct()
            {
                $this->delegate = new FakeCryptoProvider();
            }

            public function hash(string $plainPassword): string
            {
                $this->hashCalls++;

                return $this->delegate->hash($plainPassword);
            }

            public function verify(string $plainPassword, string $hashedPassword): bool
            {
                return $this->delegate->verify($plainPassword, $hashedPassword);
            }
        };
        $useCase = new RegisterUser($userRepository, $cryptoProvider);

        $input = $this->validIn();
        $useCase->execute($input);

        $secondInput = new RegisterUserIn(
            name: 'Jane Doe',
            email: $input->email,
            password: 'AnotherSecret123!',
        );

        $cryptoProvider->hashCalls = 0;

        try {
            $useCase->execute($secondInput);
            self::fail('Expected ConflictError to be thrown.');
        } catch (ConflictError $exception) {
            self::assertSame('user.email.already_used', $exception->getMessage());
        }

        // No wasted work on the failure path: hash() must not run once the duplicate is detected.
        self::assertSame(0, $cryptoProvider->hashCalls);
    }

    public function testPropagatesEntityValidationFailureAndDoesNotPersist(): void
    {
        $userRepository = new FakeUserRepository();
        $cryptoProvider = new FakeCryptoProvider();
        $useCase = new RegisterUser($userRepository, $cryptoProvider);

        $input = new RegisterUserIn(
            name: 'John',
            email: 'john.doe@example.com',
            password: 'Secret123!',
        );

        try {
            $useCase->execute($input);
            self::fail('Expected ValidationException to be thrown.');
        } catch (ValidationException $exception) {
            self::assertSame(
                ['user.name.person.name'],
                array_map(static fn ($error) => $error->getMessage(), $exception->getErrors()),
            );
        }

        self::assertNull($userRepository->findByEmail($input->email));
    }

    public function testCallsCryptoProviderHashBeforeUserRepositoryCreate(): void
    {
        $callOrder = [];

        $userRepository = new class ($callOrder) implements UserRepositoryInterface {
            private FakeUserRepository $delegate;

            /** @param array<int, string> $callOrder */
            public function __construct(private array &$callOrder)
            {
                $this->delegate = new FakeUserRepository();
            }

            public function create(mixed $data): mixed
            {
                $this->callOrder[] = 'create';

                return $this->delegate->create($data);
            }

            public function findById(string $id): mixed
            {
                return $this->delegate->findById($id);
            }

            public function findByEmail(string $email): ?User
            {
                return $this->delegate->findByEmail($email);
            }
        };

        $cryptoProvider = new class ($callOrder) implements CryptoProviderInterface {
            private FakeCryptoProvider $delegate;

            /** @param array<int, string> $callOrder */
            public function __construct(private array &$callOrder)
            {
                $this->delegate = new FakeCryptoProvider();
            }

            public function hash(string $plainPassword): string
            {
                $this->callOrder[] = 'hash';

                return $this->delegate->hash($plainPassword);
            }

            public function verify(string $plainPassword, string $hashedPassword): bool
            {
                return $this->delegate->verify($plainPassword, $hashedPassword);
            }
        };

        $useCase = new RegisterUser($userRepository, $cryptoProvider);
        $useCase->execute($this->validIn());

        self::assertSame(['hash', 'create'], $callOrder);
    }
}
