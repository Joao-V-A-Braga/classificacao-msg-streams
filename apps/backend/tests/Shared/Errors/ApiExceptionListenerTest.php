<?php

declare(strict_types=1);

namespace App\Tests\Shared\Errors;

use App\Shared\Errors\ApiExceptionListener;
use ClassificacaoMsgStreams\Shared\Error\DomainError;
use ClassificacaoMsgStreams\Shared\Error\ValidationError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ApiExceptionListenerTest extends TestCase
{
    public function testValidationExceptionMapsTo422WithStructuredErrorList(): void
    {
        $exception = new ValidationException([
            new ValidationError('email is required'),
            new ValidationError('password is too short'),
        ]);

        $body = $this->dispatch($exception);

        self::assertSame(422, $body['statusCode']);
        self::assertSame('Validation failed', $body['message']);
        self::assertSame(
            [
                ['message' => 'email is required'],
                ['message' => 'password is too short'],
            ],
            $body['details'],
        );
    }

    public function testValidationErrorMapsTo422(): void
    {
        $exception = new ValidationError('email is invalid');

        $body = $this->dispatch($exception);

        self::assertSame(422, $body['statusCode']);
        self::assertSame('email is invalid', $body['message']);
    }

    public function testDomainErrorRespectsItsOwnStatusCode(): void
    {
        $exception = new DomainError('user already exists', 409);

        $body = $this->dispatch($exception);

        self::assertSame(409, $body['statusCode']);
        self::assertSame('user already exists', $body['message']);
    }

    public function testHttpExceptionInterfacePreservesItsStatusCode(): void
    {
        $exception = new NotFoundHttpException('route not found');

        $body = $this->dispatch($exception);

        self::assertSame(404, $body['statusCode']);
        self::assertSame('route not found', $body['message']);
    }

    public function testHttpExceptionInterfaceHeadersArePreservedOnTheResponse(): void
    {
        // e.g. WWW-Authenticate on 401, Allow on 405, Retry-After on 429 —
        // clients depend on these; the listener must not drop them while
        // normalizing the body to the standard API error shape.
        $exception = new HttpException(429, 'Too many requests', null, ['Retry-After' => '30']);

        $listener = new ApiExceptionListener(new NullLogger());
        $event = $this->exceptionEvent($exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('30', $response->headers->get('Retry-After'));
    }

    public function testNonHttpExceptionsDoNotLeakAnyUnexpectedHeaders(): void
    {
        $exception = new DomainError('user already exists', 409);

        $listener = new ApiExceptionListener(new NullLogger());
        $event = $this->exceptionEvent($exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        self::assertFalse($response->headers->has('Retry-After'));
        self::assertFalse($response->headers->has('WWW-Authenticate'));
    }

    public function testUnexpectedExceptionMapsToGeneric500AndLogsTheRealError(): void
    {
        $exception = new \RuntimeException('leaking internal detail');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                self::anything(),
                self::callback(static fn (array $context): bool => ($context['exception'] ?? null) === $exception),
            );

        $body = $this->dispatch($exception, $logger);

        self::assertSame(500, $body['statusCode']);
        self::assertSame('Internal server error.', $body['message']);
        self::assertStringNotContainsString('leaking internal detail', $body['message']);
    }

    /**
     * @return array<string, mixed>
     */
    private function dispatch(\Throwable $exception, ?LoggerInterface $logger = null): array
    {
        $listener = new ApiExceptionListener($logger ?? new NullLogger());
        $event = $this->exceptionEvent($exception);

        $listener->onKernelException($event);

        $response = $event->getResponse();
        self::assertNotNull($response, 'Listener must always set a response.');

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame($response->getStatusCode(), $body['statusCode']);
        self::assertSame('/some/path', $body['path']);

        return $body;
    }

    private function exceptionEvent(\Throwable $exception): ExceptionEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/some/path');

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }
}
