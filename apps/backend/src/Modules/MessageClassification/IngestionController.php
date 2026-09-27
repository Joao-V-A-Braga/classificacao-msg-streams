<?php

declare(strict_types=1);

namespace App\Modules\MessageClassification;

use App\Modules\MessageClassification\Infrastructure\Persistence\DoctrineMessageRepository;
use App\Modules\MessageClassification\Infrastructure\Persistence\DoctrineMonitorRepository;
use App\Shared\Attribute\IsPublic;
use ClassificacaoMsgStreams\MessageClassification\Message\UseCase\ClassifyMessage;
use ClassificacaoMsgStreams\MessageClassification\Message\UseCase\ClassifyMessageIn;
use ClassificacaoMsgStreams\MessageClassification\Message\UseCase\IngestMessage;
use ClassificacaoMsgStreams\MessageClassification\Message\UseCase\IngestMessageIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\FinishMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\FinishMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\ReportMonitorRunnerState;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\ReportMonitorRunnerStateIn;
use ClassificacaoMsgStreams\Shared\Error\UnauthorizedError;
use ClassificacaoMsgStreams\Shared\Error\ValidationError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Machine-to-machine API of the ingestion runner (apps/ingestion), authenticated by a static API
 * key in X-Ingestion-Api-Key instead of the user JWT — see #[IsPublic].
 *
 * The runner polls GET /ingestion/monitors to know what to watch, reports how the watching goes and
 * posts every new message; a 404/409 answer means "stop watching this monitor".
 */
#[Route('/ingestion')]
final class IngestionController extends AbstractController
{
    public function __construct(
        private readonly DoctrineMonitorRepository $monitorRepository,
        private readonly DoctrineMessageRepository $messageRepository,
        private readonly LayaMessageClassifierProvider $messageClassifier,
        private readonly RealtimeEventPublisher $realtime,
        private readonly LoggerInterface $logger,
        private readonly IngestionRunnerHeartbeat $runnerHeartbeat,
        #[\SensitiveParameter]
        private readonly string $ingestionApiKey,
    ) {
    }

    #[IsPublic]
    #[Route('/monitors', methods: ['GET'])]
    public function runningMonitors(Request $request): JsonResponse
    {
        $this->assertValidApiKey($request);
        $this->runnerHeartbeat->beat();

        return $this->json([
            'monitors' => array_map(static fn (Monitor $monitor): array => [
                'id' => $monitor->getId(),
                'sourceType' => $monitor->getSourceType(),
                'url' => $monitor->getUrl(),
                'startedAt' => $monitor->getStartedAt()?->format(\DateTimeInterface::ATOM),
                'includeExistingComments' => $monitor->includesExistingComments(),
            ], $this->monitorRepository->findRunning()),
        ]);
    }

    /**
     * For the web app (user JWT): whether the ingestion service is polling, i.e. whether a started
     * monitor will actually be picked up.
     */
    #[Route('/status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $lastSeenAt = $this->runnerHeartbeat->lastSeenAt();

        return $this->json([
            'online' => $this->runnerHeartbeat->isOnline(),
            'lastSeenAt' => $lastSeenAt?->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[IsPublic]
    #[Route('/monitors/{id}/state', methods: ['POST'])]
    public function reportState(string $id, Request $request): JsonResponse
    {
        $this->assertValidApiKey($request);
        $body = $this->payload($request);
        $detail = is_string($body['detail'] ?? null) ? $body['detail'] : null;

        $output = (new ReportMonitorRunnerState($this->monitorRepository))->execute(
            new ReportMonitorRunnerStateIn($id, is_string($body['state'] ?? null) ? $body['state'] : '', $detail),
        );

        $data = MessageClassificationSerializer::monitor(
            $output->monitor,
            $this->messageRepository->countByLabel($output->monitor->getId()),
        );
        $this->realtime->publish($output->monitor->getUserId(), 'monitor.updated', ['monitor' => $data]);

        return $this->json(['monitor' => $data]);
    }

    /**
     * The runner gives up on a monitor whose live/post is gone: it is paused with the reason, instead
     * of staying "running" forever.
     */
    #[IsPublic]
    #[Route('/monitors/{id}/finish', methods: ['POST'])]
    public function finish(string $id, Request $request): JsonResponse
    {
        $this->assertValidApiKey($request);
        $body = $this->payload($request);

        $output = (new FinishMonitor($this->monitorRepository))->execute(
            new FinishMonitorIn($id, $this->string($body, 'reason')),
        );

        $data = MessageClassificationSerializer::monitor(
            $output->monitor,
            $this->messageRepository->countByLabel($output->monitor->getId()),
        );
        $this->realtime->publish($output->monitor->getUserId(), 'monitor.updated', ['monitor' => $data]);

        return $this->json(['monitor' => $data]);
    }

    /**
     * Ingests and classifies synchronously. The message is pushed twice to the user's tabs: as soon as
     * it is stored (so the chat shows it right away) and again once Laya has classified it. If Laya
     * fails, the message stays stored without a label instead of being lost.
     */
    #[IsPublic]
    #[Route('/monitors/{id}/messages', methods: ['POST'])]
    public function ingest(string $id, Request $request): JsonResponse
    {
        $this->assertValidApiKey($request);
        $body = $this->payload($request);

        $ingestOutput = (new IngestMessage($this->messageRepository, $this->monitorRepository))->execute(new IngestMessageIn(
            monitorId: $id,
            authorName: $this->string($body, 'authorName'),
            text: $this->string($body, 'text'),
            receivedAt: $this->date($body, 'receivedAt'),
            externalId: is_string($body['externalId'] ?? null) ? $body['externalId'] : null,
        ));

        $message = $ingestOutput->message;
        $userId = $ingestOutput->monitor->getUserId();

        if (!$ingestOutput->created) {
            return $this->json(['message' => MessageClassificationSerializer::message($message), 'duplicate' => true]);
        }

        $this->realtime->publish($userId, 'message.created', ['message' => MessageClassificationSerializer::message($message)]);

        try {
            $message = (new ClassifyMessage($this->messageRepository, $this->monitorRepository, $this->messageClassifier))
                ->execute(new ClassifyMessageIn($message->getId()))
                ->message;

            $this->realtime->publish($userId, 'message.classified', ['message' => MessageClassificationSerializer::message($message)]);
        } catch (\Throwable $exception) {
            $this->logger->error('Could not classify message {id}: {error}', [
                'id' => $message->getId(),
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }

        return $this->json(['message' => MessageClassificationSerializer::message($message)], Response::HTTP_CREATED);
    }

    private function assertValidApiKey(Request $request): void
    {
        $providedKey = (string) $request->headers->get('X-Ingestion-Api-Key', '');

        if ($this->ingestionApiKey === '' || !hash_equals($this->ingestionApiKey, $providedKey)) {
            throw new UnauthorizedError('ingestion.invalid_api_key');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (JsonException) {
            throw new BadRequestHttpException('request.invalid_json');
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function string(array $body, string $key): string
    {
        return is_string($body[$key] ?? null) ? $body[$key] : '';
    }

    /**
     * @param array<string, mixed> $body
     */
    private function date(array $body, string $key): \DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($this->string($body, $key) ?: 'now');
        } catch (\Exception) {
            throw new ValidationException([new ValidationError("message.{$key}.invalid.date")]);
        }
    }
}
