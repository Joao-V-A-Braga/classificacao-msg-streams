<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Tests\Model;

use PHPUnit\Framework\TestCase;
use ClassificacaoMsgStreams\Shared\Model\AbstractEntity;

final class SampleEntity extends AbstractEntity
{
    public function getName(): string
    {
        return $this->props['name'];
    }

    public function validate(): void
    {
        // Intentional placeholder: concrete entities implement real rules.
    }
}

final class AbstractEntityTest extends TestCase
{
    public function testGeneratesAnUuidWhenIdIsMissing(): void
    {
        $entity = new SampleEntity(['name' => 'John Doe']);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f-]{36}$/i',
            $entity->getId(),
        );
    }

    public function testCloneKeepsIdAndCreatedAtButRefreshesUpdatedAt(): void
    {
        $entity = new SampleEntity(['name' => 'John Doe']);
        $clone = $entity->clone(['name' => 'Jane Doe']);

        $this->assertSame($entity->getId(), $clone->getId());
        $this->assertSame($entity->getCreatedAt(), $clone->getCreatedAt());
        $this->assertSame('Jane Doe', $clone->getName());
        $this->assertGreaterThanOrEqual($entity->getUpdatedAt(), $clone->getUpdatedAt());
    }

    public function testEqualsComparesById(): void
    {
        $entity = new SampleEntity(['name' => 'John Doe']);
        $other = new SampleEntity(['id' => $entity->getId(), 'name' => 'Different Name']);

        $this->assertTrue($entity->equals($other));
        $this->assertFalse($entity->equals(null));
    }
}
