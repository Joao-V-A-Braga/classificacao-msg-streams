<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests\Mock;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorPageParams;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Provider\MonitorRepositoryInterface;
use ClassificacaoMsgStreams\Shared\Db\PageResult;

final class FakeMonitorRepository implements MonitorRepositoryInterface
{
    /**
     * @var array<string, Monitor>
     */
    private array $monitors = [];

    public int $updateCalls = 0;

    public function create(mixed $data): mixed
    {
        $this->monitors[$data->getId()] = $data;

        return $data;
    }

    public function update(mixed $data): mixed
    {
        if (!isset($this->monitors[$data->getId()])) {
            throw new \RuntimeException("Monitor {$data->getId()} not found.");
        }

        $this->updateCalls++;
        $this->monitors[$data->getId()] = $data;

        return $data;
    }

    public function delete(string $id): void
    {
        unset($this->monitors[$id]);
    }

    public function findById(string $id): mixed
    {
        return $this->monitors[$id] ?? null;
    }

    public function findRunning(): array
    {
        return array_values(array_filter($this->monitors, static fn (Monitor $monitor): bool => $monitor->isRunning()));
    }

    public function findPage(mixed $params): PageResult
    {
        /** @var MonitorPageParams $params */
        $items = array_values(array_filter(
            $this->monitors,
            static fn (Monitor $monitor): bool => $monitor->getUserId() === $params->userId,
        ));
        $offset = ($params->page - 1) * $params->perPage;

        return new PageResult(array_slice($items, $offset, $params->perPage), $params->page, $params->perPage, count($items));
    }
}
