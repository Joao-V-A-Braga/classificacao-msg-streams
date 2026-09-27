<?php

declare(strict_types=1);

namespace App\Modules\MessageClassification;

use ClassificacaoMsgStreams\MessageClassification\Message\Model\Message;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\Monitor;
use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;

/**
 * Response shapes shared by the HTTP API and the realtime events, so the frontend handles one format.
 */
final class MessageClassificationSerializer
{
    /**
     * @param array<string, int>|null $labelCounts number of messages per label ('' = not classified)
     * @return array<string, mixed>
     */
    public static function monitor(Monitor $monitor, ?array $labelCounts = null): array
    {
        $data = [
            'id' => $monitor->getId(),
            'name' => $monitor->getName(),
            'sourceType' => $monitor->getSourceType(),
            'url' => $monitor->getUrl(),
            'status' => $monitor->getStatus(),
            'tags' => array_map(static fn (MonitorTag $tag): array => $tag->toArray(), $monitor->getTags()),
            'startedAt' => $monitor->getStartedAt()?->format(\DateTimeInterface::ATOM),
            'includeExistingComments' => $monitor->includesExistingComments(),
            'translateForLaya' => $monitor->translatesForLaya(),
            'runnerState' => $monitor->getRunnerState(),
            'runnerDetail' => $monitor->getRunnerDetail(),
            'createdAt' => $monitor->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'updatedAt' => $monitor->getUpdatedAt()->format(\DateTimeInterface::ATOM),
        ];

        if ($labelCounts !== null) {
            $byLabel = $labelCounts;
            unset($byLabel['']);

            $data['stats'] = [
                'total' => array_sum($labelCounts),
                'unclassified' => $labelCounts[''] ?? 0,
                'byLabel' => (object) $byLabel,
            ];
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public static function message(Message $message): array
    {
        return [
            'id' => $message->getId(),
            'monitorId' => $message->getMonitorId(),
            'externalId' => $message->getExternalId(),
            'authorName' => $message->getAuthorName(),
            'text' => $message->getText(),
            'receivedAt' => $message->getReceivedAt()?->format(\DateTimeInterface::ATOM),
            'label' => $message->getLabel(),
            'labelProbabilities' => $message->getLabelProbabilities(),
            'translatedText' => $message->getTranslatedText(),
            'classifiedAt' => $message->getClassifiedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }
}
