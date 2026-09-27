<?php

declare(strict_types=1);

namespace App\Tests\Shared\Security;

use App\Shared\Security\AuthenticatedUser;
use App\Shared\Security\JwtAuthenticator;
use App\Tests\Shared\Security\Fixtures\PrivateControllerFixture;
use App\Tests\Shared\Security\Fixtures\PublicClassControllerFixture;
use App\Tests\Shared\Security\Fixtures\PublicMethodControllerFixture;
use ClassificacaoMsgStreams\Auth\User\Provider\JwtProviderInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class JwtAuthenticatorTest extends TestCase
{
    public function testSupportsReturnsFalseWhenControllerClassIsMarkedPublic(): void
    {
        $authenticator = new JwtAuthenticator($this->neverCalledJwtProvider());

        $request = $this->requestForController(PublicClassControllerFixture::class, 'index');

        self::assertFalse($authenticator->supports($request));
    }

    public function testSupportsReturnsFalseWhenControllerMethodIsMarkedPublic(): void
    {
        $authenticator = new JwtAuthenticator($this->neverCalledJwtProvider());

        $request = $this->requestForController(PublicMethodControllerFixture::class, 'open');

        self::assertFalse($authenticator->supports($request));
    }

    public function testSupportsReturnsTrueForUnmarkedActionOnAPartiallyPublicController(): void
    {
        $authenticator = new JwtAuthenticator($this->neverCalledJwtProvider());

        $request = $this->requestForController(PublicMethodControllerFixture::class, 'protected');

        self::assertTrue($authenticator->supports($request));
    }

    public function testSupportsReturnsTrueForControllerWithoutPublicAttribute(): void
    {
        $authenticator = new JwtAuthenticator($this->neverCalledJwtProvider());

        $request = $this->requestForController(PrivateControllerFixture::class, 'index');

        self::assertTrue($authenticator->supports($request));
    }

    public function testSupportsDefaultsToRequiringAuthenticationWhenControllerAttributeIsMissingOrUnresolvable(): void
    {
        // Secure by default: an unresolvable controller must never be treated as public.
        $authenticator = new JwtAuthenticator($this->neverCalledJwtProvider());

        $request = new Request();
        // No "_controller" attribute at all (e.g. before routing resolves it).

        self::assertTrue($authenticator->supports($request));
    }

    public function testAuthenticateThrowsWhenAuthorizationHeaderIsMissing(): void
    {
        $authenticator = new JwtAuthenticator($this->neverCalledJwtProvider());

        $request = new Request();

        $this->expectException(CustomUserMessageAuthenticationException::class);

        $authenticator->authenticate($request);
    }

    public function testAuthenticateThrowsWhenTokenVerificationFails(): void
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

        $authenticator = new JwtAuthenticator($provider);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer bad-token');

        $this->expectException(CustomUserMessageAuthenticationException::class);

        $authenticator->authenticate($request);
    }

    public function testAuthenticateBuildsSelfValidatingPassportFromVerifiedClaims(): void
    {
        $provider = new class implements JwtProviderInterface {
            public function generate(array $claims): string
            {
                return 'unused';
            }

            public function verify(string $token): array
            {
                TestCase::assertSame('valid-token', $token);

                return ['sub' => 'user-42', 'email' => 'user@example.com'];
            }
        };

        $authenticator = new JwtAuthenticator($provider);

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer valid-token');

        $passport = $authenticator->authenticate($request);

        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        self::assertSame('user-42', $passport->getBadge(UserBadge::class)->getUserIdentifier());

        $user = $passport->getBadge(UserBadge::class)->getUser();

        self::assertInstanceOf(AuthenticatedUser::class, $user);
        self::assertSame('user-42', $user->getUserIdentifier());
        self::assertSame('user@example.com', $user->getClaims()->get('email'));
    }

    public function testOnAuthenticationFailureReturnsA401JsonResponseWithTheApiErrorShape(): void
    {
        // Corrected behaviour (see code review fix): returning null here would let
        // AuthenticatorManager treat "no response" as "let the request continue",
        // so an unauthenticated/invalid request would silently reach the
        // controller instead of being rejected. See
        // JwtAuthenticatorRejectsUnauthenticatedRequestsTest for coverage of the
        // full AuthenticatorManager pipeline, not just this isolated call.
        $authenticator = new JwtAuthenticator($this->neverCalledJwtProvider());

        $request = Request::create('/protected/resource');
        $failureResult = $authenticator->onAuthenticationFailure(
            $request,
            new AuthenticationException('nope'),
        );

        self::assertInstanceOf(JsonResponse::class, $failureResult);
        self::assertSame(401, $failureResult->getStatusCode());

        $body = json_decode((string) $failureResult->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(401, $body['statusCode']);
        self::assertSame('Unauthorized', $body['error']);
        self::assertSame('/protected/resource', $body['path']);
    }

    public function testOnAuthenticationSuccessReturnsNullSoTheRequestContinuesToTheController(): void
    {
        $authenticator = new JwtAuthenticator($this->neverCalledJwtProvider());

        $token = $this->createStub(TokenInterface::class);

        $successResult = $authenticator->onAuthenticationSuccess(new Request(), $token, 'api');

        self::assertNull($successResult);
    }

    private function requestForController(string $class, string $method): Request
    {
        $request = new Request();
        $request->attributes->set('_controller', $class.'::'.$method);

        return $request;
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
