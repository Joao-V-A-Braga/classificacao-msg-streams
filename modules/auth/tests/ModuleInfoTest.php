<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Auth\Tests;

use ClassificacaoMsgStreams\Auth\ModuleInfo;
use PHPUnit\Framework\TestCase;

final class ModuleInfoTest extends TestCase
{
    public function testReturnsTheConfiguredModuleName(): void
    {
        self::assertSame('auth', ModuleInfo::getModuleName());
    }
}
