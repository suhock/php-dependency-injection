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
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;

/**
 * Test suite for {@see ImplementationInstanceProvider}.
 */
final class ImplementationInstanceProviderTest extends AbstractDependencyInjectionTestCase
{
    public function testGet_WithValidSubclass_ReturnsInstanceFromContextContainer(): void
    {
        // Arrange
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->expects($this->once())
            ->method('get')
            ->with(FakeClassExtendsBaseClass::class)
            ->willReturn(new FakeClassExtendsBaseClass());

        $factory = new ImplementationInstanceProvider(
            FakeBaseClass::class,
            FakeClassExtendsBaseClass::class
        );

        // Act
        $instance = $factory->get(self::createResolutionContext(container: $container));

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
    }

    public function testGet_WhenImplementationSameAsInterface_ThrowsImplementationException(): void
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

    public function testGet_WhenImplementationNotSubclassOfInterface_ThrowsImplementationException(): void
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
