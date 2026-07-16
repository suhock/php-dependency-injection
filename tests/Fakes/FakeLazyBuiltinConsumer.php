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
 * Misuses #[Lazy] on a builtin-typed parameter, which the container can never construct lazily. The default keeps the
 * parameter soft so the misuse surfaces on its own, without a separate unresolvable-parameter defect.
 */
final class FakeLazyBuiltinConsumer
{
    public function __construct(
        #[Lazy]
        public readonly int $number = 0,
    ) {}
}
