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
 * Counts how many times {@see FakeLazyService} has been constructed, so tests can prove a lazily injected dependency
 * is not built until first used.
 */
final class FakeLazyCounter
{
    public int $constructions = 0;
}
