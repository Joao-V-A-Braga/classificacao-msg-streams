<?php

declare(strict_types=1);

namespace App\Tests\Shared\Security;

use App\Shared\Security\JwtAuthenticator;
use App\Tests\Shared\Security\Fixtures\PrivateControllerFixture;
use ClassificacaoMsgStreams\Auth\User\Provider\JwtProviderInterface;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Http\Authentication\AuthenticatorManager;

/**
 * Regression coverage for a critical bug found in code review: the original
 * JwtAuthenticator::onAuthenticationFailure() returned null, which means
 * Symfony Security treats "no response" as "let the request continue" —
 * AuthenticatorManager::authenticateRequest() would return null and the
 * request would reach the controller completely unauthenticated, instead of
 * being rejected with 401.
 *
 * These tests drive the REAL Symfony\Component\Security\Http\Authentication\
 * AuthenticatorManager (not just a bare call to onAuthenticationFailure() in
 * isolation), so they prove the actual authentication pipeline rejects the
 * request with a 401 — the same pipeline the "api" firewall in
 * security.yaml wires JwtAuthenticator into.
 */
final class JwtAuthenticatorRejectsUnauthenticatedRequestsTest extends TestCase
{
    public function testRequestWithoutAuthorizationHeaderIsRejectedWith401(): void
    {
        $response = $this->authenticateRequestFor($this->neverCalledJwtProvider(), new Request());

        self::assertNotNull($response, 'A protected route without a token must not reach the controller.');
        self::assertSame(401, $response->getStatusCode());

        $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(401, $body['statusCode']);
        self::assertSame('Unauthorized', $body['error']);
    }

    public function testRequestWithForgedTokenIsRejectedWith401(): void
    {
        $provider = new class implements JwtProviderInterface {
            public function generate(array $claims): string
            {
                return 'unused';
            }

            public function verify(string $token): array
            {
                throw new \RuntimeException('Invalid or expired token.');
            }
        };

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer forged-token');

        $response = $this->authenticateRequestFor($provider, $request);

        self::assertNotNull($response, 'A protected route with a forged token must not reach the controller.');
        self::assertSame(401, $response->getStatusCode());
    }

    public function testRequestWithExpiredTokenIsRejectedWith401(): void
    {
        $secret = 'test-secret-expired-0123456789abcdef';
        $expiredToken = JWT::encode(
            ['sub' => 'user-123', 'iat' => time() - 7200, 'exp' => time() - 3600],
            $secret,
            'HS256',
        );

        $provider = new class($secret) implements JwtProviderInterface {
            public function __construct(private readonly string $secret)
            {
            }

            public function generate(array $claims): string
            {
                return 'unused';
            }

            public function verify(string $token): array
            {
                try {
                    $decoded = JWT::decode($token, new \Firebase\JWT\Key($this->secret, 'HS256'));
                } catch (\Throwable $exception) {
                    throw new \RuntimeException('Invalid or expired token.', 0, $exception);
                }

                return (array) $decoded;
            }
        };

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer '.$expiredToken);

        $response = $this->authenticateRequestFor($provider, $request);

        self::assertNotNull($response, 'A protected route with an expired token must not reach the controller.');
        self::assertSame(401, $response->getStatusCode());
    }

    public function testRequestWithValidTokenIsNotRejected(): void
    {
        $provider = new class implements JwtProviderInterface {
            public function generate(array $claims): string
            {
                return 'unused';
            }

            public function verify(string $token): array
            {
                return ['sub' => 'user-42'];
            }
        };

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer valid-token');

        $response = $this->authenticateRequestFor($provider, $request);

        self::assertNull($response, 'A protected route with a valid token must let the request continue to the controller.');
    }

    /**
     * Runs the request through a real AuthenticatorManager wired with a
     * single JwtAuthenticator, on a route that is NOT #[IsPublic]
     * (PrivateControllerFixture), exactly mirroring how the "api" firewall
     * drives JwtAuthenticator in security.yaml.
     */
    private function authenticateRequestFor(JwtProviderInterface $jwtProvider, Request $request): ?\Symfony\Component\HttpFoundation\Response
    {
        $request->attributes->set('_controller', PrivateControllerFixture::class.'::index');

        $authenticator = new JwtAuthenticator($jwtProvider);

        $manager = new AuthenticatorManager(
            [$authenticator],
            new TokenStorage(),
            new EventDispatcher(),
            'api',
        );

        // Mirrors what the real AuthenticatorManagerListener does on kernel.request:
        // call supports() first (populates _security_authenticators), then authenticateRequest().
        $manager->supports($request);

        return $manager->authenticateRequest($request);
    }

    private function neverCalledJwtProvider(): JwtProviderInterface
    {
        return new class implements JwtProviderInterface {
            public function generate(array $claims): string
            {
                throw new \LogicException('Should not be called in this test.');
            }

            public function verify(string $token): array
            {
                throw new \LogicException('Should not be called in this test.');
            }
        };
    }
}
