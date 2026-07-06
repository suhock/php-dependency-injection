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
use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;

/**
 * Test suite for {@see ObjectInstanceProvider}.
 */
final class ObjectInstanceProviderTest extends AbstractDependencyInjectionTestCase
{
    public function testConstruct_WhenInstanceIsNotAnInstanceOfClass_ThrowsInstanceTypeException(): void
    {
        // Arrange & Act
        $fn = static fn () => new ObjectInstanceProvider(
            FakeClassExtendsBaseClass::class,
            new FakeClassNoConstructor()
        );

        // Assert
        self::assertThrowsInstanceTypeException(
            FakeClassExtendsBaseClass::class,
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
        $expectedInstance = new FakeClassExtendsBaseClass();
        $factory = new ObjectInstanceProvider(FakeBaseClass::class, $expectedInstance);

        // Act
        $instance = $factory->get();

        // Assert
        self::assertSame($expectedInstance, $instance);
    }
}
