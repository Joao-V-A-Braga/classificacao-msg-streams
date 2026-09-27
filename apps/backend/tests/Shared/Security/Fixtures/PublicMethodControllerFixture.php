<?php

declare(strict_types=1);

namespace App\Tests\Shared\Security\Fixtures;

use App\Shared\Attribute\IsPublic;

final class PublicMethodControllerFixture
{
    #[IsPublic]
    public function open(): void
    {
    }

    public function protected(): void
    {
    }
}
