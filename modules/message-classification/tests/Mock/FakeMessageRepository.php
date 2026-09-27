<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Mock;

use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessagePageParams;
use ClassificacaoMsgStreams\MessageClassification\Message\Provider\MessageRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Db\PageResult;

final class FakeMessageRepository implements MessageRepositoryInterface
{
    /**
     * @var array<string, Message>
     */
    private array $messages = [];

    public function create(mixed $data): mixed
    {
        $this->messages[$data->getId()] = $data;

        return $data;
    }

    public function update(mixed $data): mixed
    {
        if (!isset($this->messages[$data->getId()])) {
            throw new \RuntimeException("Message {$data->getId()} not found.");
        }

        $this->messages[$data->getId()] = $data;

        return $data;
    }

    public function delete(string $id): void
    {
        unset($this->messages[$id]);
    }

    public function findById(string $id): mixed
    {
        return $this->messages[$id] ?? null;
    }

    public function findByExternalId(string $monitorId, string $externalId): ?Message
    {
        foreach ($this->messages as $message) {
            if ($message->getMonitorId() === $monitorId && $message->getExternalId() === $externalId) {
                return $message;
            }
        }

        return null;
    }

    public function deleteByMonitorId(string $monitorId): void
    {
        $this->messages = array_filter(
            $this->messages,
            static fn (Message $message): bool => $message->getMonitorId() !== $monitorId,
        );
    }

    public function deleteByLabel(string $monitorId, string $label): int
    {
        $before = count($this->messages);
        $this->messages = array_filter(
            $this->messages,
            static fn (Message $message): bool => $message->getMonitorId() !== $monitorId || $message->getLabel() !== $label,
        );

        return $before - count($this->messages);
    }

    public function countByLabel(string $monitorId): array
    {
        $counts = [];
        foreach ($this->messages as $message) {
            if ($message->getMonitorId() === $monitorId) {
                $label = $message->getLabel() ?? '';
                $counts[$label] = ($counts[$label] ?? 0) + 1;
            }
        }

        return $counts;
    }

    public function findPage(mixed $params): PageResult
    {
        /** @var MessagePageParams $params */
        $items = array_values(array_filter(
            $this->messages,
            static fn (Message $message): bool => $message->getMonitorId() === $params->monitorId
                && ($params->labels === [] || in_array($message->getLabel(), $params->labels, true)),
        ));
        $offset = ($params->page - 1) * $params->perPage;

        return new PageResult(
            items: array_slice($items, $offset, $params->perPage),
            page: $params->page,
            perPage: $params->perPage,
            total: count($items),
        );
    }
}
