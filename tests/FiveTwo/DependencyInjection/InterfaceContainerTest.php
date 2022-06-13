<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

/**
 * Test suite for {@see InterfaceContainer}.
 */
class InterfaceContainerTest extends DependencyInjectionTestCase
{
    public function testGet(): void
    {
        $container = self::createMock(ContainerInterface::class);
        $container->method('get')
            ->with(FakeClassNoConstructor::class)
            ->willReturn(new FakeClassNoConstructor());
        $container->method('has')
            ->with(FakeClassNoConstructor::class)
            ->willReturn(true);
        $injector = new Injector($container);

        $implContainer = new InterfaceContainer(
            FakeClassNoConstructor::class,
            $injector,
            /** @param class-string $className */
            fn (string $className) => $injector->instantiate($className)
        );

        self::assertInstanceOf(
            FakeClassExtendsNoConstructor::class,
            $implContainer->get(FakeClassExtendsNoConstructor::class)
        );
    }

    public function testGet_SameClass(): void
    {
        $container = new InterfaceContainer(
            FakeClassNoConstructor::class,
            self::createMock(InjectorInterface::class),
            fn () => null
        );

        self::assertUnresolvedClassException(
            FakeClassNoConstructor::class,
            fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testGet_NotSubclass(): void
    {
        $container = new InterfaceContainer(
            FakeClassNoConstructor::class,
            self::createMock(InjectorInterface::class),
            fn () => null
        );

        self::assertUnresolvedClassException(
            FakeClassUsingContexts::class,
            /** @psalm-suppress InvalidArgument Testing for invalid argument here */
            fn () => $container->get(FakeClassUsingContexts::class)
        );
    }

    public function testHas(): void
    {
        $container = new InterfaceContainer(
            FakeClassNoConstructor::class,
            self::createMock(InjectorInterface::class),
            fn () => null
        );

        self::assertTrue($container->has(FakeClassExtendsNoConstructor::class));
        self::assertFalse($container->has(FakeClassNoConstructor::class));
        /** @psalm-suppress InvalidArgument */
        self::assertFalse($container->has(FakeClassUsingContexts::class));
    }
}
