<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provision;

use Suhock\DependencyInjection\ContainerInjector;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\DependencyInjectionTestCase;
use Suhock\DependencyInjection\FakeClassExtendsNoConstructor;
use Suhock\DependencyInjection\FakeClassNoConstructor;
use Suhock\DependencyInjection\InjectorInterface;

/**
 * Test suite for {@see ClosureInstanceProvider}.
 */
class ClosureInstanceProviderTest extends DependencyInjectionTestCase
{
    public function testGet_WithFactoryFunction_ReturnsValueFromFactoryFunction(): void
    {
        $factory = new ClosureInstanceProvider(
            FakeClassNoConstructor::class,
            $factoryMethod = static fn () => new FakeClassNoConstructor(),
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn () => $factoryMethod());

        self::assertInstanceOf(FakeClassNoConstructor::class, $factory->get());
    }

    public function testGet_WhenFactoryReturnsNull_ThrowsInstanceTypeException(): void
    {
        $factory = new ClosureInstanceProvider(
            FakeClassNoConstructor::class,
            $factoryMethod = static fn () => null,
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn () => $factoryMethod());

        self::assertThrowsInstanceTypeException(
            FakeClassNoConstructor::class,
            null,
            static fn () => self::assertNull($factory->get())
        );
    }

    public function testGet_WhenFactoryReturnsWrongType_ThrowsInstanceTypeException(): void
    {
        $factory = new ClosureInstanceProvider(
            FakeClassExtendsNoConstructor::class,
            fn () => new FakeClassNoConstructor(),
            new ContainerInjector($this->createStub(ContainerInterface::class))
        );

        self::assertThrowsInstanceTypeException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            static fn () => $factory->get()
        );
    }
}
