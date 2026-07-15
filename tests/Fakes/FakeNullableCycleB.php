<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

/**
 * Fakes the soft half of a dependency cycle: the reference back to {@see FakeNullableCycleA} is nullable, so it
 * self-heals to <code>null</code> at runtime.
 */
final class FakeNullableCycleB
{
    public function __construct(public readonly ?FakeNullableCycleA $a) {}
}
