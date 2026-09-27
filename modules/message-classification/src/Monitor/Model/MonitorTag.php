<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Monitor\Model;

use ClassificacaoMsgStreams\Shared\Validation\Rules\MaxLengthRule;
use ClassificacaoMsgStreams\Shared\Validation\Rules\RequiredRule;
use ClassificacaoMsgStreams\Shared\Validation\Validator;

/**
 * A classification tag of a monitor: Laya picks one tag per message. `name` is what the user sees
 * (and what gets stored as the message label); `description` is the optional hint sent to Laya.
 */
final class MonitorTag
{
    public const NAME_MAX_LENGTH = 40;

    public const DESCRIPTION_MAX_LENGTH = 120;

    public readonly string $name;

    public readonly ?string $description;

    public function __construct(string $name, ?string $description = null)
    {
        $this->name = self::normalize($name);
        $description = $description !== null ? self::normalize($description) : null;
        $this->description = $description === '' ? null : $description;
    }

    public function isNamed(string $name): bool
    {
        return mb_strtolower($this->name) === mb_strtolower(self::normalize($name));
    }

    public function validate(): void
    {
        Validator::validate([
            [
                'code' => 'monitor.tag.name',
                'value' => $this->name,
                'rules' => [new RequiredRule(), new MaxLengthRule(self::NAME_MAX_LENGTH)],
            ],
            [
                'code' => 'monitor.tag.description',
                'value' => $this->description,
                'rules' => [new MaxLengthRule(self::DESCRIPTION_MAX_LENGTH)],
            ],
        ]);
    }

    /**
     * @return array{name: string, description: string|null}
     */
    public function toArray(): array
    {
        return ['name' => $this->name, 'description' => $this->description];
    }

    /**
     * @param array{name?: mixed, description?: mixed} $data
     */
    public static function fromArray(array $data): self
    {
        return new self((string) ($data['name'] ?? ''), isset($data['description']) ? (string) $data['description'] : null);
    }

    private static function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
