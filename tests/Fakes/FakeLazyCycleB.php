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
 * The other half of the cycle with {@see FakeLazyCycleA}, depending on it through an ordinary (eager) edge.
 */
final class FakeLazyCycleB
{
    public function __construct(public readonly FakeLazyCycleA $a) {}
}
