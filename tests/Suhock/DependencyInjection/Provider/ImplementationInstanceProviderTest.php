<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provider;

use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\DependencyInjectionTestCase;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;

/**
 * Test suite for {@see ImplementationInstanceProvider}.
 */
class ImplementationInstanceProviderTest extends DependencyInjectionTestCase
{
    public function testGet_WithValidSubclass_ReturnsInstanceOfSubclass(): void
    {
        // Arrange
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->expects(self::once())
            ->method('get')
            ->with(FakeClassExtendsNoConstructor::class)
            ->willReturn(new FakeClassExtendsNoConstructor());

        $factory = new ImplementationInstanceProvider(
            FakeClassNoConstructor::class,
            FakeClassExtendsNoConstructor::class,
            $container
        );

        // Act
        $instance = $factory->get();

        // Assert
        self::assertInstanceOf(FakeClassExtendsNoConstructor::class, $instance);
    }

    public function testGet_WhenImplementationSameAsInterface_ThrowsImplementationException(): void
    {
        // Arrange & Act
        $fn = fn () => new ImplementationInstanceProvider(
            FakeClassNoConstructor::class,
            FakeClassNoConstructor::class,
            $this->createStub(ContainerInterface::class)
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
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            $this->createStub(ContainerInterface::class)
        );

        // Assert
        self::assertThrowsImplementationException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            $fn
        );
    }
}
