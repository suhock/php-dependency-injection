<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\FakeNoConstructorClass;
use FiveTwo\DependencyInjection\FakeNoConstructorSubclass;
use FiveTwo\DependencyInjection\InjectorInterface;
use PHPUnit\Framework\TestCase;

class ClosureInstanceFactoryTest extends TestCase
{
    public function testGet(): void
    {
        $factory = new ClosureInstanceFactory(
            FakeNoConstructorClass::class,
            $factoryMethod = fn () => new FakeNoConstructorClass(),
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects(self::once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn () => $factoryMethod());

        self::assertInstanceOf(FakeNoConstructorClass::class, $factory->get());
    }

    public function testGet_Null(): void
    {
        $factory = new ClosureInstanceFactory(
            FakeNoConstructorClass::class,
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
        $factory = new ClosureInstanceFactory(
            FakeNoConstructorSubclass::class,
            $factoryMethod = fn () => new FakeNoConstructorClass(),
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
