<?php

declare(strict_types=1);

namespace App\Modules\MessageClassification;

use App\Modules\MessageClassification\Infrastructure\Persistence\DoctrineMessageRepository;
use App\Modules\MessageClassification\Infrastructure\Persistence\DoctrineMonitorRepository;
use App\Shared\Security\AuthenticatedUser;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessagePageParams;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorPageParams;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\AddMonitorTag;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\AddMonitorTagIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\ConfigureMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\ConfigureMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\CreateMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\CreateMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\DeleteMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\DeleteMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\PauseMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\PauseMonitorIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\RemoveMonitorTag;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\RemoveMonitorTagIn;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\StartMonitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\UseCase\StartMonitorIn;
use ClassificacaoMsgStreams\Shared\Error\NotFoundError;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The user's monitors (one pipeline per live/post) and their messages. Every change is also pushed
 * to the user's open tabs through the realtime hub.
 */
#[Route('/monitors')]
final class MonitorController extends AbstractController
{
    public function __construct(
        private readonly DoctrineMonitorRepository $monitorRepository,
        private readonly DoctrineMessageRepository $messageRepository,
        private readonly RealtimeEventPublisher $realtime,
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(100, $request->query->getInt('perPage', 50)));

        $result = $this->monitorRepository->findPage(new MonitorPageParams($user->getUserIdentifier(), $page, $perPage));

        return $this->json([
            'items' => array_map(
                fn (Monitor $monitor): array => MessageClassificationSerializer::monitor(
                    $monitor,
                    $this->messageRepository->countByLabel($monitor->getId()),
                ),
                $result->items,
            ),
            'page' => $result->page,
            'perPage' => $result->perPage,
            'total' => $result->total,
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $body = $this->payload($request);
        $tags = is_array($body['tags'] ?? null) ? array_values(array_filter($body['tags'], 'is_array')) : [];

        $output = (new CreateMonitor($this->monitorRepository))->execute(new CreateMonitorIn(
            userId: $user->getUserIdentifier(),
            name: $this->string($body, 'name'),
            sourceType: $this->string($body, 'sourceType'),
            url: $this->string($body, 'url'),
            tags: $tags,
            includeExistingComments: ($body['includeExistingComments'] ?? false) === true,
            translateForLaya: ($body['translateForLaya'] ?? true) !== false,
        ));

        return $this->respondWithMonitor($output->monitor, Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(string $id, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        return $this->json(['monitor' => $this->serializeWithStats($this->findOwned($id, $user))]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id, #[CurrentUser] AuthenticatedUser $user): Response
    {
        $output = (new DeleteMonitor($this->monitorRepository, $this->messageRepository))
            ->execute(new DeleteMonitorIn($id, $user->getUserIdentifier()));

        $this->realtime->publish($output->monitor->getUserId(), 'monitor.deleted', ['monitorId' => $output->monitor->getId()]);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/start', methods: ['POST'])]
    public function start(string $id, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $output = (new StartMonitor($this->monitorRepository))->execute(new StartMonitorIn($id, $user->getUserIdentifier()));

        return $this->respondWithMonitor($output->monitor);
    }

    #[Route('/{id}/pause', methods: ['POST'])]
    public function pause(string $id, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $output = (new PauseMonitor($this->monitorRepository))->execute(new PauseMonitorIn($id, $user->getUserIdentifier()));

        return $this->respondWithMonitor($output->monitor);
    }

    /**
     * Monitor settings; only the fields sent change. `includeExistingComments` (Instagram) only while
     * paused; `translateForLaya` any time (applies from the next message).
     */
    #[Route('/{id}/settings', methods: ['PATCH'])]
    public function configure(string $id, Request $request, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $body = $this->payload($request);

        $output = (new ConfigureMonitor($this->monitorRepository))->execute(new ConfigureMonitorIn(
            monitorId: $id,
            userId: $user->getUserIdentifier(),
            includeExistingComments: is_bool($body['includeExistingComments'] ?? null) ? $body['includeExistingComments'] : null,
            translateForLaya: is_bool($body['translateForLaya'] ?? null) ? $body['translateForLaya'] : null,
        ));

        return $this->respondWithMonitor($output->monitor);
    }

    #[Route('/{id}/tags', methods: ['POST'])]
    public function addTag(string $id, Request $request, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $body = $this->payload($request);
        $description = $this->string($body, 'description');

        $output = (new AddMonitorTag($this->monitorRepository))->execute(new AddMonitorTagIn(
            monitorId: $id,
            userId: $user->getUserIdentifier(),
            name: $this->string($body, 'name'),
            description: $description !== '' ? $description : null,
        ));

        return $this->respondWithMonitor($output->monitor);
    }

    #[Route('/{id}/tags/{name}', methods: ['DELETE'])]
    public function removeTag(string $id, string $name, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $output = (new RemoveMonitorTag($this->monitorRepository, $this->messageRepository))
            ->execute(new RemoveMonitorTagIn($id, $user->getUserIdentifier(), $name));

        return $this->respondWithMonitor($output->monitor, extra: ['deletedMessages' => $output->deletedMessages]);
    }

    #[Route('/{id}/messages', methods: ['GET'])]
    public function messages(string $id, Request $request, #[CurrentUser] AuthenticatedUser $user): JsonResponse
    {
        $monitor = $this->findOwned($id, $user);
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = max(1, min(200, $request->query->getInt('perPage', 50)));
        // Any of several tags: ?label[]=pergunta&label[]=elogio (a single ?label=pergunta also works).
        $labels = $request->query->all()['label'] ?? [];
        $labels = array_filter(is_array($labels) ? $labels : [$labels], 'is_string');

        $result = $this->messageRepository->findPage(
            new MessagePageParams($monitor->getId(), $page, $perPage, $labels),
        );

        return $this->json([
            'items' => array_map(MessageClassificationSerializer::message(...), $result->items),
            'page' => $result->page,
            'perPage' => $result->perPage,
            'total' => $result->total,
        ]);
    }

    /**
     * Reads by id answer 404 for someone else's monitor too, so ids of other users never leak.
     */
    private function findOwned(string $id, AuthenticatedUser $user): Monitor
    {
        $monitor = $this->monitorRepository->findById($id);

        if ($monitor === null || $monitor->getUserId() !== $user->getUserIdentifier()) {
            throw new NotFoundError('monitor.not_found');
        }

        return $monitor;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWithStats(Monitor $monitor): array
    {
        return MessageClassificationSerializer::monitor($monitor, $this->messageRepository->countByLabel($monitor->getId()));
    }

    /**
     * @param array<string, mixed> $extra more fields for the response and the realtime event
     */
    private function respondWithMonitor(Monitor $monitor, int $status = Response::HTTP_OK, array $extra = []): JsonResponse
    {
        $body = ['monitor' => $this->serializeWithStats($monitor), ...$extra];
        $this->realtime->publish($monitor->getUserId(), 'monitor.updated', $body);

        return $this->json($body, $status);
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
}
