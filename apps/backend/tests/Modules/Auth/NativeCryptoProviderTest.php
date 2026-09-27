<?php

declare(strict_types=1);

namespace App\Tests\Modules\Auth;

use App\Modules\Auth\NativeCryptoProvider;
use PHPUnit\Framework\TestCase;

final class NativeCryptoProviderTest extends TestCase
{
    public function testHashProducesValidBcryptStringThatVerifiesSuccessfully(): void
    {
        $provider = new NativeCryptoProvider();

        $hash = $provider->hash('correct-horse-battery-staple');

        self::assertMatchesRegularExpression('/^\$2y?\$/', $hash);
        self::assertTrue($provider->verify('correct-horse-battery-staple', $hash));
    }

    public function testVerifyReturnsFalseForWrongPassword(): void
    {
        $provider = new NativeCryptoProvider();

        $hash = $provider->hash('correct-horse-battery-staple');

        self::assertFalse($provider->verify('wrong-password', $hash));
    }

    public function testHashProducesDifferentStringsForTheSamePasswordDueToRandomSalt(): void
    {
        $provider = new NativeCryptoProvider();

        $firstHash = $provider->hash('correct-horse-battery-staple');
        $secondHash = $provider->hash('correct-horse-battery-staple');

        self::assertNotSame($firstHash, $secondHash);
        self::assertTrue($provider->verify('correct-horse-battery-staple', $firstHash));
        self::assertTrue($provider->verify('correct-horse-battery-staple', $secondHash));
    }
}
