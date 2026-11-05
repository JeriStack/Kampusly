<?php

declare(strict_types=1);

namespace Kampusly\Shared\Application\Contract;

use Closure;

interface TransactionManagerInterface
{
    /**
     * Execute the given callback inside an atomic database transaction.
     *
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function transactional(Closure $operation): mixed;
}

