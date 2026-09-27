<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\Model;

use ClassificacaoMsgStreams\Shared\Error\ForbiddenError;
use ClassificacaoMsgStreams\Shared\Error\ValidationError;
use ClassificacaoMsgStreams\Shared\Error\ValidationException;
use ClassificacaoMsgStreams\Shared\Model\AbstractEntity;
use ClassificacaoMsgStreams\Shared\Validation\Rules\ChoiceRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\DateRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\MaxLengthRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\RequiredRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\UrlRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\UuidRule;
use ClassificacaoMsgStreams\Shared\Validation\Validator;

/**
 * A pipeline that watches one live or post and classifies its new messages with the monitor's tags.
 */
final class Monitor extends AbstractEntity
{
    public const SOURCE_YOUTUBE_LIVE = 'youtube_live';

    public const SOURCE_INSTAGRAM_POST = 'instagram_post';

    public const SOURCE_TYPES = [self::SOURCE_YOUTUBE_LIVE, self::SOURCE_INSTAGRAM_POST];

    public const STATUS_PAUSED = 'paused';

    public const STATUS_RUNNING = 'running';

    public const STATUSES = [self::STATUS_PAUSED, self::STATUS_RUNNING];

    /** What the ingestion runner reports while the monitor is running. */
    public const RUNNER_STATES = ['starting', 'watching', 'error'];

    /** Laya needs at least two options to choose from. */
    public const MIN_TAGS_TO_START = 2;

    public const MAX_TAGS = 10;

    private const SOURCE_HOSTS = [
        self::SOURCE_YOUTUBE_LIVE => ['youtube.com', 'youtu.be'],
        self::SOURCE_INSTAGRAM_POST => ['instagram.com'],
    ];

    public function getUserId(): string
    {
        return $this->props['userId'];
    }

    public function getName(): string
    {
        return $this->props['name'];
    }

    public function getSourceType(): string
    {
        return $this->props['sourceType'];
    }

    public function getUrl(): string
    {
        return $this->props['url'];
    }

    public function getStatus(): string
    {
        return $this->props['status'] ?? self::STATUS_PAUSED;
    }

    /**
     * @return MonitorTag[]
     */
    public function getTags(): array
    {
        return $this->props['tags'] ?? [];
    }

    /**
     * Last time the monitor was started; only messages that show up after it are ingested.
     */
    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->props['startedAt'] ?? null;
    }

    public function getRunnerState(): ?string
    {
        return $this->props['runnerState'] ?? null;
    }

    /**
     * Instagram posts only: on start, also classify the comments the post already had (not just the
     * ones written after the start).
     */
    public function includesExistingComments(): bool
    {
        return (bool) ($this->props['includeExistingComments'] ?? false);
    }

    /**
     * Messages are translated to English before Laya classifies them (more accurate). On by default;
     * the user can turn it off, e.g. when the text model is slow.
     */
    public function translatesForLaya(): bool
    {
        return (bool) ($this->props['translateForLaya'] ?? true);
    }

    public function supportsExistingComments(): bool
    {
        return $this->getSourceType() === self::SOURCE_INSTAGRAM_POST;
    }

    public function getRunnerDetail(): ?string
    {
        return $this->props['runnerDetail'] ?? null;
    }

    public function isRunning(): bool
    {
        return $this->getStatus() === self::STATUS_RUNNING;
    }

    public function findTag(string $name): ?MonitorTag
    {
        foreach ($this->getTags() as $tag) {
            if ($tag->isNamed($name)) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    public function getTagNames(): array
    {
        return array_map(static fn (MonitorTag $tag): string => $tag->name, $this->getTags());
    }

    public function assertOwnedBy(string $userId): void
    {
        if ($this->getUserId() !== $userId) {
            throw new ForbiddenError('monitor.forbidden');
        }
    }

    public function validate(): void
    {
        Validator::validate([
            [
                'code' => 'monitor.userId',
                'value' => $this->getUserId(),
                'rules' => [new RequiredRule(), new UuidRule()],
            ],
            [
                'code' => 'monitor.name',
                'value' => $this->getName(),
                'rules' => [new RequiredRule(), new MaxLengthRule(120)],
            ],
            [
                'code' => 'monitor.sourceType',
                'value' => $this->getSourceType(),
                'rules' => [new RequiredRule(), new ChoiceRule(self::SOURCE_TYPES)],
            ],
            [
                'code' => 'monitor.url',
                'value' => $this->getUrl(),
                'rules' => [new RequiredRule(), new MaxLengthRule(2048), new UrlRule()],
            ],
            [
                'code' => 'monitor.status',
                'value' => $this->getStatus(),
                'rules' => [new RequiredRule(), new ChoiceRule(self::STATUSES)],
            ],
            [
                'code' => 'monitor.tags',
                'value' => $this->getTags(),
                'rules' => [new MaxLengthRule(self::MAX_TAGS)],
            ],
            [
                'code' => 'monitor.startedAt',
                'value' => $this->getStartedAt(),
                'rules' => [new DateRule()],
            ],
            [
                'code' => 'monitor.runnerState',
                'value' => $this->getRunnerState(),
                'rules' => [new ChoiceRule(self::RUNNER_STATES)],
            ],
            [
                'code' => 'monitor.runnerDetail',
                'value' => $this->getRunnerDetail(),
                'rules' => [new MaxLengthRule(500)],
            ],
        ]);

        $this->validateUrlMatchesSource();
        $this->validateTags();

        if ($this->includesExistingComments() && !$this->supportsExistingComments()) {
            // A live chat has no history to import: it only exists while the live runs.
            throw new ValidationException([new ValidationError('monitor.includeExistingComments.not_supported')]);
        }
    }

    private function validateUrlMatchesSource(): void
    {
        $hosts = self::SOURCE_HOSTS[$this->getSourceType()] ?? [];
        $host = strtolower((string) parse_url($this->getUrl(), PHP_URL_HOST));

        foreach ($hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return;
            }
        }

        throw new ValidationException([new ValidationError('monitor.url.source.mismatch')]);
    }

    private function validateTags(): void
    {
        $seen = [];
        foreach ($this->getTags() as $tag) {
            $tag->validate();

            $key = mb_strtolower($tag->name);
            if (isset($seen[$key])) {
                throw new ValidationException([new ValidationError('monitor.tag.name.duplicated')]);
            }
            $seen[$key] = true;
        }
    }
}
