<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Tests\Validation\Rules;

use PHPUnit\Framework\TestCase;
use ClassificacaoMsgStreams\Shared\Validation\Rules\UrlRule;

final class UrlRuleTest extends TestCase
{
    public function testPassesForHttpAndHttpsUrls(): void
    {
        $rule = new UrlRule();

        $this->assertNull($rule->validate('https://www.youtube.com/watch?v=abc'));
        $this->assertNull($rule->validate('http://example.com'));
    }

    public function testFailsForOtherSchemes(): void
    {
        $this->assertSame('invalid.url', (new UrlRule())->validate('ftp://example.com/file'));
        $this->assertSame('invalid.url', (new UrlRule())->validate('javascript://alert(1)'));
    }

    public function testFailsForTextThatIsNotAUrl(): void
    {
        $this->assertSame('invalid.url', (new UrlRule())->validate('youtube.com/watch'));
        $this->assertSame('invalid.url', (new UrlRule())->validate(42));
    }

    public function testTreatsEmptyValueAsValid(): void
    {
        $this->assertNull((new UrlRule())->validate(null));
        $this->assertNull((new UrlRule())->validate(''));
    }
}
