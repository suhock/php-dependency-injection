<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\InstanceProvision;

use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\DependencyInjectionTestCase;
use FiveTwo\DependencyInjection\FakeClassExtendsNoConstructor;
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use FiveTwo\DependencyInjection\Injector;
use FiveTwo\DependencyInjection\InjectorInterface;

/**
 * Test suite for {@see ClosureInstanceProvider}.
 */
class ClosureInstanceProviderTest extends DependencyInjectionTestCase
{
    public function testGet_CallsFactory(): void
    {
        $factory = new ClosureInstanceProvider(
            FakeClassNoConstructor::class,
            $factoryMethod = fn() => new FakeClassNoConstructor(),
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn() => $factoryMethod());

        self::assertInstanceOf(FakeClassNoConstructor::class, $factory->get());
    }

    public function testGet_FactoryReturnsNull(): void
    {
        $factory = new ClosureInstanceProvider(
            FakeClassNoConstructor::class,
            $factoryMethod = fn() => null,
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn() => $factoryMethod());

        self::assertNull($factory->get());
    }

    public function testGet_Exception_FactoryReturnsWrongType(): void
    {
        $factory = new ClosureInstanceProvider(
            FakeClassExtendsNoConstructor::class,
            fn() => new FakeClassNoConstructor(),
            new Injector(self::createStub(ContainerInterface::class))
        );

        self::assertInstanceTypeException(
            FakeClassExtendsNoConstructor::class,
            FakeClassNoConstructor::class,
            fn() => $factory->get()
        );
    }
}
