<?php

declare(strict_types=1);

namespace App\Tests\Shared\Security\Fixtures;

use App\Shared\Attribute\IsPublic;

#[IsPublic]
final class PublicClassControllerFixture
{
    public function index(): void
    {
    }
}
