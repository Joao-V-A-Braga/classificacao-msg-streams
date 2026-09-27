<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\Tests\Validation\Rules;

use PHPUnit\Framework\TestCase;
use ClassificacaoMsgStreams\Shared\Validation\Rules\ChoiceRule;

final class ChoiceRuleTest extends TestCase
{
    public function testPassesWhenValueIsInTheAllowedList(): void
    {
        $rule = new ChoiceRule(['youtube', 'instagram']);

        $this->assertNull($rule->validate('youtube'));
    }

    public function testFailsWhenValueIsNotInTheAllowedList(): void
    {
        $rule = new ChoiceRule(['youtube', 'instagram']);

        $this->assertSame('invalid.choice', $rule->validate('tiktok'));
    }

    public function testIsStrictAboutTypeNotJustLooseEquality(): void
    {
        $rule = new ChoiceRule([1, 2, 3]);

        $this->assertSame('invalid.choice', $rule->validate('1'));
        $this->assertNull($rule->validate(1));
    }

    public function testTreatsEmptyValueAsValid(): void
    {
        $rule = new ChoiceRule(['youtube']);

        $this->assertNull($rule->validate(null));
        $this->assertNull($rule->validate(''));
    }
}
