<?php

declare(strict_types=1);

namespace Kampusly\Tests\Support;

use Closure;
use Kampusly\Shared\Application\Contract\TransactionManagerInterface;

final class NullTransactionManager implements TransactionManagerInterface
{
    public function transactional(Closure $operation): mixed
    {
        return $operation();
    }
}

