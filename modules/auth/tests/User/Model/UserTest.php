<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\Tests\User\Model;

use ClassificacaoMsgStreams\Auth\User\Model\User;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validProps(array $overrides = []): array
    {
        return array_merge([
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => password_hash('Secret123!', PASSWORD_BCRYPT),
        ], $overrides);
    }

    /**
     * @return string[]
     */
    private function errorCodes(callable $callback): array
    {
        try {
            $callback();

            return [];
        } catch (ValidationException $exception) {
            return array_map(static fn ($error) => $error->getMessage(), $exception->getErrors());
        }
    }

    public function testCreatesAValidEntityAndExposesAllGetters(): void
    {
        $props = $this->validProps();
        $user = new User($props);

        self::assertSame($props['name'], $user->getName());
        self::assertSame($props['email'], $user->getEmail());
        self::assertSame($props['password'], $user->getPassword());
    }

    public function testAllowsATemporarilyInvalidEntityBeforeValidateIsCalled(): void
    {
        $this->expectNotToPerformAssertions();

        new User($this->validProps([
            'name' => '',
            'email' => 'not-an-email',
            'password' => 'plain-text-password',
        ]));
    }

    public function testValidatePassesForAFullyValidEntity(): void
    {
        $this->expectNotToPerformAssertions();

        $user = new User($this->validProps());
        $user->validate();
    }

    public function testValidateFailsWhenNameIsEmpty(): void
    {
        $user = new User($this->validProps(['name' => '']));

        self::assertSame(
            ['user.name.required'],
            $this->errorCodes(fn () => $user->validate()),
        );
    }

    public function testValidateFailsWhenNameHasOnlyOnePart(): void
    {
        $user = new User($this->validProps(['name' => 'John']));

        self::assertSame(
            ['user.name.person.name'],
            $this->errorCodes(fn () => $user->validate()),
        );
    }

    public function testValidateFailsWhenNameExceedsMaxLength(): void
    {
        $user = new User($this->validProps([
            'name' => str_repeat('A', 200).' '.str_repeat('B', 100),
        ]));

        self::assertSame(
            ['user.name.max.length'],
            $this->errorCodes(fn () => $user->validate()),
        );
    }

    public function testValidateFailsWhenEmailIsEmpty(): void
    {
        $user = new User($this->validProps(['email' => '']));

        self::assertSame(
            ['user.email.required'],
            $this->errorCodes(fn () => $user->validate()),
        );
    }

    public function testValidateFailsWhenEmailIsMalformed(): void
    {
        $user = new User($this->validProps(['email' => 'not-an-email']));

        self::assertSame(
            ['user.email.invalid.email'],
            $this->errorCodes(fn () => $user->validate()),
        );
    }

    public function testValidateFailsWhenEmailExceedsMaxLength(): void
    {
        $user = new User($this->validProps([
            'email' => str_repeat('a', 250).'@example.com',
        ]));

        self::assertSame(
            ['user.email.max.length'],
            $this->errorCodes(fn () => $user->validate()),
        );
    }

    public function testValidateFailsWhenPasswordIsEmpty(): void
    {
        $user = new User($this->validProps(['password' => '']));

        self::assertSame(
            ['user.password.required'],
            $this->errorCodes(fn () => $user->validate()),
        );
    }

    public function testValidateFailsWhenPasswordIsNotAHash(): void
    {
        $user = new User($this->validProps(['password' => 'plain-text-password']));

        self::assertSame(
            ['user.password.invalid.password_hash'],
            $this->errorCodes(fn () => $user->validate()),
        );
    }

    public function testValidateFailsWithAllFieldErrorsWhenEverythingIsInvalid(): void
    {
        $user = new User($this->validProps([
            'name' => '',
            'email' => '',
            'password' => '',
        ]));

        self::assertSame(
            ['user.name.required', 'user.email.required', 'user.password.required'],
            $this->errorCodes(fn () => $user->validate()),
        );
    }

    public function testEqualsReturnsTrueForEntitiesSharingTheSameId(): void
    {
        $props = $this->validProps(['id' => '018f0c1a-1111-7000-8000-000000000001']);
        $user = new User($props);
        $sameUser = new User($props);

        self::assertTrue($user->equals($sameUser));
    }

    public function testEqualsReturnsFalseForEntitiesWithDifferentIds(): void
    {
        $user = new User($this->validProps(['id' => '018f0c1a-1111-7000-8000-000000000001']));
        $otherUser = new User($this->validProps(['id' => '018f0c1a-2222-7000-8000-000000000002']));

        self::assertFalse($user->equals($otherUser));
    }

    public function testEqualsReturnsFalseWhenComparedToNull(): void
    {
        $user = new User($this->validProps());

        self::assertFalse($user->equals(null));
    }

    public function testCloneKeepsIdentityAndOverridesProvidedFields(): void
    {
        $user = new User($this->validProps());

        $cloned = $user->clone(['name' => 'Jane Doe']);

        self::assertInstanceOf(User::class, $cloned);
        self::assertTrue($user->equals($cloned));
        self::assertSame('Jane Doe', $cloned->getName());
        self::assertSame($user->getEmail(), $cloned->getEmail());
        self::assertSame($user->getPassword(), $cloned->getPassword());
        self::assertGreaterThanOrEqual($user->getUpdatedAt(), $cloned->getUpdatedAt());
    }
}
