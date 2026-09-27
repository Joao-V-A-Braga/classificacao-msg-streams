<?php

declare(strict_types=1);

namespace App\Shared\Attribute;

use Attribute;

/**
 * Marca uma rota (classe de controller ou método de ação) como aberta,
 * dispensando autenticação JWT. Por padrão, todas as rotas são autenticadas;
 * este atributo é a única forma de abrir uma exceção a essa regra.
 *
 * Nota: o nome canônico deste atributo, tanto no brief da tarefa quanto na
 * skill `backend-symfony-config`, é `#[Public]`. Isso não é sintaxe PHP
 * válida: `public` é uma palavra reservada da linguagem e não pode nomear
 * uma classe (nem mesmo via alias de `use ... as Public`) — confirmado
 * empiricamente com `php -l`. `IsPublic` é o nome mais próximo que preserva
 * a intenção original e compila.
 *
 * Lido por App\Shared\Security\JwtAuthenticator::supports().
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
final class IsPublic
{
}
