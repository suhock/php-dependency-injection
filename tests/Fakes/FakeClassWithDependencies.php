<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use RuntimeException;
use Throwable;

/**
 * Fakes a simple class with multiple constructor dependencies.
 */
final class FakeClassWithDependencies
{
    public readonly Throwable $throwable;

    public function __construct(
        Throwable $throwable,
        public readonly RuntimeException $runtimeException,
    ) {
        $this->throwable = $throwable;
    }
}
