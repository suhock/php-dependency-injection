<?php

/*
 * Copyright (c) 2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Fakes;

/**
 * Fakes a class exposing factory methods reachable as a callable string and as a callable array.
 */
final class FakeStaticFactory
{
    public static function create(): FakeClassExtendsBaseClass
    {
        return new FakeClassExtendsBaseClass();
    }

    public function make(): FakeClassExtendsBaseClass
    {
        return new FakeClassExtendsBaseClass();
    }
}
