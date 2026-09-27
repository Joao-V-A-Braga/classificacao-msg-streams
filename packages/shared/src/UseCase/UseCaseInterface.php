<?php

declare(strict_types=1);

namespace ClassificacaoMsgStreams\Shared\UseCase;

/**
 * @template TIn
 * @template TOut
 */
interface UseCaseInterface
{
    /**
     * @param TIn $input
     * @return TOut
     */
    public function execute(mixed $input): mixed;
}
