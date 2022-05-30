<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\FakeNoConstructorClass;
use FiveTwo\DependencyInjection\FakeNoConstructorSubclass;
use PHPUnit\Framework\TestCase;

class ImplementationInstanceFactoryTest extends TestCase
{
    public function testGet(): void
    {
        $factory = new ImplementationInstanceFactory(
            FakeNoConstructorClass::class,
            FakeNoConstructorSubclass::class,
            $container = $this->createMock(ContainerInterface::class)
        );

        $container->method('has')->willReturn(true);
        $container->expects(self::once())
            ->method('get')
            ->with(FakeNoConstructorSubclass::class)
            ->willReturn(new FakeNoConstructorSubclass());

        self::assertInstanceOf(FakeNoConstructorSubclass::class, $factory->get());
    }

    public function testGet_SameClass(): void
    {
        self::expectException(ImplementationException::class);
        new ImplementationInstanceFactory(
            FakeNoConstructorClass::class,
            FakeNoConstructorClass::class,
            $this->createMock(ContainerInterface::class)
        );
    }

    public function testGet_WrongClass(): void
    {
        self::expectException(ImplementationException::class);
        new ImplementationInstanceFactory(
            FakeNoConstructorSubclass::class,
            FakeNoConstructorClass::class,
            $this->createMock(ContainerInterface::class)
        );
    }
}
