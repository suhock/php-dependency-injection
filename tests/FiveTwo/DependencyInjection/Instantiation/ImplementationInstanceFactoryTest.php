<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\NoConstructorTestClass;
use FiveTwo\DependencyInjection\NoConstructorTestSubClass;
use PHPUnit\Framework\TestCase;

class ImplementationInstanceFactoryTest extends TestCase
{
    public function testGet(): void
    {
        $factory = new ImplementationInstanceFactory(
            NoConstructorTestClass::class,
            NoConstructorTestSubClass::class,
            $container = $this->createMock(ContainerInterface::class)
        );

        $container->method('has')->willReturn(true);
        $container->expects($this->once())
            ->method('get')
            ->with(NoConstructorTestSubClass::class)
            ->willReturn(new NoConstructorTestSubClass());

        self::assertInstanceOf(NoConstructorTestSubClass::class, $factory->get());
    }

    public function testGet_SameClass(): void
    {
        self::expectException(ImplementationException::class);
        new ImplementationInstanceFactory(
            NoConstructorTestClass::class,
            NoConstructorTestClass::class,
            $this->createMock(ContainerInterface::class)
        );
    }

    public function testGet_WrongClass(): void
    {
        self::expectException(ImplementationException::class);
        new ImplementationInstanceFactory(
            NoConstructorTestSubClass::class,
            NoConstructorTestClass::class,
            $this->createMock(ContainerInterface::class)
        );
    }
}
