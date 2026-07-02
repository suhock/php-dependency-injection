<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provision;

use Suhock\DependencyInjection\DependencyInjectionTestCase;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;

/**
 * Test suite for {@see ObjectInstanceProvider}.
 */
class ObjectInstanceProviderTest extends DependencyInjectionTestCase
{
    public function testConstruct_WhenInstanceIsNotAnInstanceOfClass_ThrowsInstanceTypeException(): void
    {
        // Arrange & Act
        $fn = static fn () => new ObjectInstanceProvider(
            FakeClassExtendsNoConstructor::class,
            new FakeClassNoConstructor()
        );

        // Assert
        self::assertThrowsInstanceTypeException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            $fn
        );
    }

    public function testGet_WithInstanceOfSameClass_ReturnsSameInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $factory = new ObjectInstanceProvider(FakeClassNoConstructor::class, $expectedInstance);

        // Act
        $instance = $factory->get();

        // Assert
        self::assertSame($expectedInstance, $instance);
    }

    public function testGet_WithInstanceOfSubclass_ReturnsSameInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassExtendsNoConstructor();
        $factory = new ObjectInstanceProvider(FakeClassNoConstructor::class, $expectedInstance);

        // Act
        $instance = $factory->get();

        // Assert
        self::assertSame($expectedInstance, $instance);
    }
}
