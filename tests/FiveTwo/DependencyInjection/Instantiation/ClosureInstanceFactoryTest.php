<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\InjectorInterface;
use FiveTwo\DependencyInjection\NoConstructorTestClass;
use FiveTwo\DependencyInjection\NoConstructorTestSubClass;
use PHPUnit\Framework\TestCase;

class ClosureInstanceFactoryTest extends TestCase
{
    public function testGet(): void
    {
        $factory = new ClosureInstanceFactory(
            NoConstructorTestClass::class,
            $factoryMethod = fn() => new NoConstructorTestClass(),
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects($this->once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn() => $factoryMethod());

        self::assertInstanceOf(NoConstructorTestClass::class, $factory->get());
    }

    public function testGet_Null(): void
    {
        $factory = new ClosureInstanceFactory(
            NoConstructorTestClass::class,
            $factoryMethod = fn() => null,
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects($this->once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturnCallback(fn() => $factoryMethod());

        self::assertNull($factory->get());
    }

    public function testGet_WrongClass(): void
    {
        $factory = new ClosureInstanceFactory(
            NoConstructorTestSubClass::class,
            $factoryMethod = fn() => new NoConstructorTestClass(),
            $injector = $this->createMock(InjectorInterface::class)
        );

        $injector->expects($this->once())
            ->method('call')
            ->with($factoryMethod)
            ->willReturn(fn() => $factoryMethod());

        self::expectException(DependencyTypeException::class);
        $factory->get();
    }
}
