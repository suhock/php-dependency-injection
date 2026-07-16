<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

use Suhock\DependencyInjection\Lazy;

/**
 * One half of a dependency cycle with {@see FakeLazyCycleB} in which this edge is lazy, so the cycle is broken: the
 * dependency is not constructed while this service is.
 */
final class FakeLazyCycleA
{
    public function __construct(
        #[Lazy]
        public readonly FakeLazyCycleB $b,
    ) {}
}
