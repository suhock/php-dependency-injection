<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\InstanceProvider;

use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;

/**
 * Test suite for {@see ImplementationInstanceProvider}.
 */
final class ImplementationInstanceProviderTest extends AbstractDependencyInjectionTestCase
{
    public function testConstruct_WhenImplementationSameAsInterface_ThrowsImplementationException(): void
    {
        // Arrange & Act
        $fn = fn () => new ImplementationInstanceProvider(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            $fn
        );
    }

    public function testConstruct_WhenImplementationNotSubclassOfInterface_ThrowsImplementationException(): void
    {
        // Arrange & Act
        $fn = fn () => new ImplementationInstanceProvider(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassExtendsBaseClass::class,
            FakeClassNoConstructor::class,
            $fn
        );
    }
}
