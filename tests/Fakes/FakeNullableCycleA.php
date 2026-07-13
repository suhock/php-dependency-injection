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
 * Fakes one half of a dependency cycle whose closing edge is soft: this class requires {@see FakeNullableCycleB},
 * which takes a nullable reference back.
 */
final class FakeNullableCycleA
{
    public function __construct(public readonly FakeNullableCycleB $b)
    {
    }
}
