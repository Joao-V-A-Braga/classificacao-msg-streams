<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Message\Model;

use ClassificacaoMsgStreams\MessageClassification\Monitor\Model\MonitorTag;
use ClassificacaoMsgStreams\Shared\Model\AbstractEntity;
use ClassificacaoMsgStreams\Shared\Validation\Rules\DateRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\MaxLengthRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\RequiredRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\UuidRule;
use ClassificacaoMsgStreams\Shared\Validation\Validator;

/**
 * A chat message or comment collected by a monitor. `label` is one of the monitor's tags, picked by Laya.
 */
final class Message extends AbstractEntity
{
    public function getMonitorId(): string
    {
        return $this->props['monitorId'];
    }

    /**
     * Id of the message on its platform (e.g. the YouTube chat item id), used to never ingest it twice.
     */
    public function getExternalId(): ?string
    {
        return $this->props['externalId'] ?? null;
    }

    public function getAuthorName(): string
    {
        return $this->props['authorName'];
    }

    public function getText(): string
    {
        return $this->props['text'];
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->props['receivedAt'] ?? null;
    }

    public function getLabel(): ?string
    {
        return $this->props['label'] ?? null;
    }

    /**
     * @return array<string, float>|null probability of each tag, as returned by the classifier
     */
    public function getLabelProbabilities(): ?array
    {
        return $this->props['labelProbabilities'] ?? null;
    }

    /**
     * The English version Laya classified (it is more accurate in English); null when the original
     * text was classified as is.
     */
    public function getTranslatedText(): ?string
    {
        return $this->props['translatedText'] ?? null;
    }

    public function getClassifiedAt(): ?\DateTimeImmutable
    {
        return $this->props['classifiedAt'] ?? null;
    }

    public function validate(): void
    {
        Validator::validate([
            [
                'code' => 'message.monitorId',
                'value' => $this->getMonitorId(),
                'rules' => [new RequiredRule(), new UuidRule()],
            ],
            [
                'code' => 'message.externalId',
                'value' => $this->getExternalId(),
                'rules' => [new MaxLengthRule(255)],
            ],
            [
                'code' => 'message.authorName',
                'value' => $this->getAuthorName(),
                'rules' => [new RequiredRule(), new MaxLengthRule(255)],
            ],
            [
                'code' => 'message.text',
                'value' => $this->getText(),
                'rules' => [new RequiredRule(), new MaxLengthRule(5000)],
            ],
            [
                'code' => 'message.receivedAt',
                'value' => $this->getReceivedAt(),
                'rules' => [new RequiredRule(), new DateRule()],
            ],
            [
                'code' => 'message.label',
                'value' => $this->getLabel(),
                'rules' => [new MaxLengthRule(MonitorTag::NAME_MAX_LENGTH)],
            ],
            [
                'code' => 'message.translatedText',
                'value' => $this->getTranslatedText(),
                'rules' => [new MaxLengthRule(20000)],
            ],
            [
                'code' => 'message.classifiedAt',
                'value' => $this->getClassifiedAt(),
                'rules' => [new DateRule()],
            ],
        ]);
    }
}
