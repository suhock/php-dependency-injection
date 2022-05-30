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
        $container = self::createMock(ContainerInterface::class);
        $container->method('get')
            ->with(FakeNoConstructorClass::class)
            ->willReturn(new FakeNoConstructorClass());
        $container->method('has')
            ->with(FakeNoConstructorClass::class)
            ->willReturn(true);
        $injector = new Injector($container);

        $implContainer = new ImplementationContainer(
            FakeNoConstructorClass::class,
            $injector,
            /** @param class-string $className */
            fn (string $className) => $injector->instantiate($className)
        );

        self::assertInstanceOf(
            FakeNoConstructorSubclass::class,
            $implContainer->get(FakeNoConstructorSubclass::class)
        );
    }

    public function testGet_SameClass(): void
    {
        $container = new ImplementationContainer(
            FakeNoConstructorClass::class,
            self::createMock(InjectorInterface::class),
            fn () => null
        );

        self::expectException(UnresolvedClassException::class);
        $container->get(FakeNoConstructorClass::class);
    }

    public function testGet_NotSubclass(): void
    {
        $container = new ImplementationContainer(
            FakeNoConstructorClass::class,
            self::createMock(InjectorInterface::class),
            fn () => null
        );

        self::expectException(UnresolvedClassException::class);
        /** @psalm-suppress InvalidArgument Testing for invalid argument here */
        $container->get(FakeContextAwareClass::class);
    }

    public function testHas(): void
    {
        $container = new ImplementationContainer(
            FakeNoConstructorClass::class,
            self::createMock(InjectorInterface::class),
            fn () => null
        );

        self::assertTrue($container->has(FakeNoConstructorSubclass::class));
        self::assertFalse($container->has(FakeNoConstructorClass::class));
        /** @psalm-suppress InvalidArgument */
        self::assertFalse($container->has(FakeContextAwareClass::class));
    }
}
