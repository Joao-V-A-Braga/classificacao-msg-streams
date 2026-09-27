<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\MessageClassification\Tests;

use ClassificacaoMsgStreams\MessageClassification\ModuleInfo;
use PHPUnit\Framework\TestCase;

final class ModuleInfoTest extends TestCase
{
    public function testReturnsTheConfiguredModuleName(): void
    {
        self::assertSame('message-classification', ModuleInfo::getModuleName());
    }
}
