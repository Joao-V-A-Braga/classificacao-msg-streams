<?php

declare(strict_types=1);

namespace App\Tests\Modules\Auth;

use App\Modules\Auth\FirebaseJwtProvider;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

final class FirebaseJwtProviderTest extends TestCase
{
    public function testGenerateAndVerifyRoundTripPreservesClaims(): void
    {
        $provider = new FirebaseJwtProvider('test-secret-round-trip-0123456789abcdef');

        $token = $provider->generate([
            'sub' => 'user-123',
            'email' => 'user@example.com',
        ]);

        $claims = $provider->verify($token);

        self::assertSame('user-123', $claims['sub']);
        self::assertSame('user@example.com', $claims['email']);
        self::assertArrayHasKey('iat', $claims);
        self::assertArrayHasKey('exp', $claims);
        self::assertSame($claims['iat'] + 3600, $claims['exp']);
    }

    public function testVerifyThrowsRuntimeExceptionForTamperedToken(): void
    {
        $provider = new FirebaseJwtProvider('test-secret-tampered-0123456789abcdef');

        $token = $provider->generate(['sub' => 'user-123']);

        // Flip a character in the signature segment to invalidate the token.
        $segments = explode('.', $token);
        $lastSegment = $segments[2];
        $lastSegment[0] = $lastSegment[0] === 'A' ? 'B' : 'A';
        $segments[2] = $lastSegment;
        $tamperedToken = implode('.', $segments);

        $this->expectException(\RuntimeException::class);

        $provider->verify($tamperedToken);
    }

    public function testVerifyThrowsRuntimeExceptionForExpiredToken(): void
    {
        $secret = 'test-secret-expired-0123456789abcdef';
        $provider = new FirebaseJwtProvider($secret);

        $expiredToken = JWT::encode(
            [
                'sub' => 'user-123',
                'iat' => time() - 7200,
                'exp' => time() - 3600,
            ],
            $secret,
            'HS256',
        );

        $this->expectException(\RuntimeException::class);

        $provider->verify($expiredToken);
    }
}
