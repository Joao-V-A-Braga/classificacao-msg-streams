<?php

declare(strict_types=1);

namespace App\Shared\Security;

use App\Shared\Attribute\IsPublic;
use App\Shared\Errors\ApiErrorResponse;
use ClassificacaoMsgStreams\Auth\User\Provider\JwtProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Autenticador JWT compartilhado da API. Rotas são autenticadas por padrão;
 * o atributo #[IsPublic] (em classe ou método do controller) é a única forma
 * de dispensar autenticação para uma rota.
 *
 * Delega 100% da verificação criptográfica/expiração do token para
 * JwtProviderInterface::verify() — nunca reimplementa parsing/validação de
 * JWT aqui.
 */
final class JwtAuthenticator extends AbstractAuthenticator
{
    public function __construct(private readonly JwtProviderInterface $jwtProvider)
    {
    }

    public function supports(Request $request): ?bool
    {
        return !$this->isPublic($request);
    }

    public function authenticate(Request $request): Passport
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            throw new CustomUserMessageAuthenticationException('Missing authentication token.');
        }

        try {
            $payload = $this->jwtProvider->verify($token);
        } catch (\Throwable $exception) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired token.', [], 0, $exception);
        }

        $subject = (string) ($payload['sub'] ?? '');

        if ($subject === '') {
            throw new CustomUserMessageAuthenticationException('Invalid authentication token.');
        }

        return new SelfValidatingPassport(
            new UserBadge($subject, static fn (): AuthenticatedUser => new AuthenticatedUser($payload)),
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        // NAO retornar null aqui. `null` nao propaga a AuthenticationException
        // para o ApiExceptionListener (KernelEvents::EXCEPTION nunca dispara
        // nesse caminho) e, sem access_control configurado no firewall (que
        // bloquearia tambem as rotas #[IsPublic], que nunca apresentam
        // token), a requisicao simplesmente segue sem autenticacao ate o
        // controller. Confirmado lendo o codigo-fonte real do
        // symfony/security-http instalado (AuthenticatorManager,
        // AccessListener). Devolver a resposta 401 diretamente aqui é a
        // forma correta e minimamente invasiva de rejeitar (usar
        // `access_control: [{ path: '^/', roles: IS_AUTHENTICATED_FULLY }]`
        // NAO é a correção certa: isso bloquearia também as rotas
        // `#[IsPublic]`, que nunca têm token).
        return new JsonResponse(
            (new ApiErrorResponse(
                statusCode: 401,
                error: 'Unauthorized',
                message: $exception->getMessageKey(),
                path: $request->getPathInfo(),
            ))->toArray(),
            401,
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    private function extractToken(Request $request): ?string
    {
        $header = $request->headers->get('Authorization');

        if ($header === null || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, strlen('Bearer ')));

        return $token !== '' ? $token : null;
    }

    private function isPublic(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');

        if (!is_string($controller) || !str_contains($controller, '::')) {
            return false;
        }

        [$class, $method] = explode('::', $controller, 2);

        if (!class_exists($class)) {
            return false;
        }

        try {
            $classReflection = new \ReflectionClass($class);

            if ($classReflection->getAttributes(IsPublic::class) !== []) {
                return true;
            }

            if ($classReflection->hasMethod($method)) {
                $methodReflection = $classReflection->getMethod($method);

                if ($methodReflection->getAttributes(IsPublic::class) !== []) {
                    return true;
                }
            }
        } catch (\ReflectionException) {
            return false;
        }

        return false;
    }
}
