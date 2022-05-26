<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use PHPUnit\Framework\TestCase;

class ImplementationContainerTest extends TestCase
{
    public function testGet(): void
    {
        $container = self::createMock(DependencyContainerInterface::class);
        $container->method('get')
            ->with(NoConstructorTestClass::class)
            ->willReturn(new NoConstructorTestClass());
        $container->method('has')
            ->with(NoConstructorTestClass::class)
            ->willReturn(true);
        $injector = new DependencyInjector($container);

        $implContainer = new ImplementationContainer(
            NoConstructorTestClass::class,
            $injector,
            /** @param class-string $className */
            fn(string $className) => $injector->instantiate($className)
        );

        self::assertInstanceOf(
            NoConstructorTestSubClass::class,
            $implContainer->get(NoConstructorTestSubClass::class)
        );
    }

    public function testGet_SameClass(): void
    {
        $container = new ImplementationContainer(
            NoConstructorTestClass::class, self::createMock(DependencyInjectorInterface::class), fn() => null
        );

        self::expectException(UnresolvedClassException::class);
        $container->get(NoConstructorTestClass::class);
    }

    public function testGet_NotSubclass(): void
    {
        $container = new ImplementationContainer(
            NoConstructorTestClass::class, self::createMock(DependencyInjectorInterface::class), fn() => null
        );

        self::expectException(UnresolvedClassException::class);
        $container->get(ConstructorTestClass::class);
    }

    public function testHas(): void
    {
        $container = new ImplementationContainer(
            NoConstructorTestClass::class, self::createMock(DependencyInjectorInterface::class), fn() => null
        );

        self::assertTrue($container->has(NoConstructorTestSubClass::class));
        self::assertFalse($container->has(NoConstructorTestClass::class));
        /** @psalm-suppress InvalidArgument */
        self::assertFalse($container->has(ConstructorTestClass::class));
    }
}
