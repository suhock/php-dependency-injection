<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use RuntimeException;
use Throwable;

class ConstructorTestClass
{
    public function __construct(
        public readonly Throwable $throwable,
        public readonly RuntimeException $runtimeException
    ) {
    }
}
