<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provider;

use Suhock\DependencyInjection\AbstractDependencyInjectionTestCase;
use Suhock\DependencyInjection\ContainerInjector;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\InjectorInterface;

/**
 * Test suite for {@see ClosureInstanceProvider}.
 */
final class ClosureInstanceProviderTest extends AbstractDependencyInjectionTestCase
{
    public function testGet_WithFactoryFunction_ReturnsValueFromFactoryFunction(): void
    {
        // Arrange
        $factory = new ClosureInstanceProvider(
            FakeClassNoConstructor::class,
            $factoryMethod = static fn () => new FakeClassNoConstructor(),
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects($this->once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn () => $factoryMethod());

        // Act
        $instance = $factory->get();

        // Assert
        self::assertInstanceOf(FakeClassNoConstructor::class, $instance);
    }

    public function testGet_WhenFactoryReturnsNull_ThrowsInstanceTypeException(): void
    {
        // Arrange
        $factory = new ClosureInstanceProvider(
            FakeClassNoConstructor::class,
            $factoryMethod = static fn () => null,
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects($this->once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn () => $factoryMethod());

        // Act
        $fn = static fn () => $factory->get();

        // Assert
        self::assertThrowsInstanceTypeException(
            FakeClassNoConstructor::class,
            null,
            $fn
        );
    }

    public function testGet_WhenFactoryReturnsWrongType_ThrowsInstanceTypeException(): void
    {
        // Arrange
        $factory = new ClosureInstanceProvider(
            FakeClassExtendsNoConstructor::class,
            fn () => new FakeClassNoConstructor(),
            new ContainerInjector(self::createStub(ContainerInterface::class))
        );

        // Act
        $fn = static fn () => $factory->get();

        // Assert
        self::assertThrowsInstanceTypeException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            $fn
        );
    }
}
