<?php

declare(strict_types=1);

namespace App\Shared\Errors;

use ClassificacaoMsgStreams\Shared\Error\DomainError;
use ClassificacaoMsgStreams\Shared\Error\ValidationError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Ponto único de tradução de exceções em respostas HTTP da API.
 *
 * Reconhece, nesta ordem de prioridade:
 *  1. ValidationException (packages/shared)  -> 422 com lista estruturada de erros
 *  2. ValidationError (packages/shared)      -> 422
 *  3. DomainError (packages/shared)          -> status definido por getStatusCode()
 *  4. HttpExceptionInterface (Symfony)       -> status original preservado
 *  5. Qualquer outra Throwable               -> 500 genérico, com log do erro real
 *
 * Nenhum controller deve fazer try/catch manual apenas para traduzir erros:
 * essa responsabilidade vive inteiramente aqui.
 */
final class ApiExceptionListener implements EventSubscriberInterface
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $path = $event->getRequest()->getPathInfo();

        $errorResponse = match (true) {
            $exception instanceof ValidationException => $this->fromValidationException($exception, $path),
            $exception instanceof ValidationError => $this->fromValidationError($exception, $path),
            $exception instanceof DomainError => $this->fromDomainError($exception, $path),
            $exception instanceof HttpExceptionInterface => $this->fromHttpException($exception, $path),
            default => $this->fromUnexpectedException($exception, $path),
        };

        // HttpExceptionInterface pode carregar headers semanticamente
        // relevantes (WWW-Authenticate em 401, Allow em 405, Retry-After em
        // 429, ...); repassar sempre para não perder essa informação.
        // Nas demais branches não há headers a preservar, então o padrão
        // (array vazio) é aplicado de forma consistente.
        $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

        $event->setResponse(new JsonResponse($errorResponse->toArray(), $errorResponse->statusCode, $headers));
    }

    private function fromValidationException(ValidationException $exception, string $path): ApiErrorResponse
    {
        $details = array_map(
            static fn (ValidationError $error): array => ['message' => $error->getMessage()],
            $exception->getErrors(),
        );

        return new ApiErrorResponse(
            statusCode: $exception->getStatusCode(),
            error: $this->statusText($exception->getStatusCode()),
            message: $exception->getMessage(),
            details: $details,
            path: $path,
        );
    }

    private function fromValidationError(ValidationError $exception, string $path): ApiErrorResponse
    {
        return new ApiErrorResponse(
            statusCode: $exception->getStatusCode(),
            error: $this->statusText($exception->getStatusCode()),
            message: $exception->getMessage(),
            path: $path,
        );
    }

    private function fromDomainError(DomainError $exception, string $path): ApiErrorResponse
    {
        return new ApiErrorResponse(
            statusCode: $exception->getStatusCode(),
            error: $this->statusText($exception->getStatusCode()),
            message: $exception->getMessage(),
            path: $path,
        );
    }

    private function fromHttpException(HttpExceptionInterface $exception, string $path): ApiErrorResponse
    {
        return new ApiErrorResponse(
            statusCode: $exception->getStatusCode(),
            error: $this->statusText($exception->getStatusCode()),
            message: $exception->getMessage() !== '' ? $exception->getMessage() : $this->statusText($exception->getStatusCode()),
            path: $path,
        );
    }

    private function fromUnexpectedException(\Throwable $exception, string $path): ApiErrorResponse
    {
        $this->logger->error('Unhandled exception: {message}', [
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        return new ApiErrorResponse(
            statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            error: $this->statusText(Response::HTTP_INTERNAL_SERVER_ERROR),
            message: 'Internal server error.',
            path: $path,
        );
    }

    private function statusText(int $statusCode): string
    {
        return Response::$statusTexts[$statusCode] ?? 'Error';
    }
}
