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
 * Fakes an invokable instance of the class it would also serve as a factory for, the one source shape whose reading is
 * ambiguous.
 */
final class FakeInvokableBaseClass extends FakeBaseClass
{
    public function __invoke(): FakeBaseClass
    {
        return new FakeClassExtendsBaseClass();
    }
}
