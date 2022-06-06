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

use FiveTwo\DependencyInjection\FakeClassExtendsNoConstructor;
use FiveTwo\DependencyInjection\FakeClassNoConstructor;
use FiveTwo\DependencyInjection\InjectorInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see ClosureInstanceProvider}.
 */
class ClosureInstanceProviderTest extends TestCase
{
    public function testGet(): void
    {
        $factory = new ClosureInstanceProvider(
            FakeClassNoConstructor::class,
            $factoryMethod = fn () => new FakeClassNoConstructor(),
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn () => $factoryMethod());

        self::assertInstanceOf(FakeClassNoConstructor::class, $factory->get());
    }

    public function testGet_Null(): void
    {
        $factory = new ClosureInstanceProvider(
            FakeClassNoConstructor::class,
            $factoryMethod = fn () => null,
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn () => $factoryMethod());

        self::assertNull($factory->get());
    }

    public function testGet_WrongClass(): void
    {
        $factory = new ClosureInstanceProvider(
            FakeClassExtendsNoConstructor::class,
            $factoryMethod = fn () => new FakeClassNoConstructor(),
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturn(fn () => $factoryMethod());

        self::expectException(InstanceTypeException::class);
        $factory->get();
    }
}
