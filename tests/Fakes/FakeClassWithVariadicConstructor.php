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
 * Fakes a class whose constructor takes a variadic parameter. The fast instantiation path cannot spread arguments, so
 * it declines the class and the reflection path instantiates it instead.
 */
final class FakeClassWithVariadicConstructor
{
    /** @var array<array-key, FakeClassNoConstructor> */
    public readonly array $items;

    public function __construct(FakeClassNoConstructor ...$items)
    {
        $this->items = $items;
    }
}
