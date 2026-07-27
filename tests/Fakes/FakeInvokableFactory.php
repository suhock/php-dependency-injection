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
 * Fakes an invokable object usable as a factory, of no relation to the class it produces.
 */
final class FakeInvokableFactory
{
    public function __invoke(): FakeClassExtendsBaseClass
    {
        return new FakeClassExtendsBaseClass();
    }
}
