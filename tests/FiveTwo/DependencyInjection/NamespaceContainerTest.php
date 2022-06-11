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

use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for {@see NamespaceContainer}.
 */
class NamespaceContainerTest extends TestCase
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

        $namespaceContainer = new NamespaceContainer(
            __NAMESPACE__,
            $injector,
            /** @param class-string $className */
            fn (string $className) => $injector->instantiate($className)
        );

        self::assertInstanceOf(
            FakeClassNoConstructor::class,
            $namespaceContainer->get(FakeClassNoConstructor::class)
        );
    }

    public function testGet_ClassNotInNamespace(): void
    {
        $container = new NamespaceContainer(
            __NAMESPACE__,
            self::createMock(InjectorInterface::class),
            fn () => null
        );

        $this->expectException(UnresolvedClassException::class);
        $container->get(DateTime::class);
    }

    public function testHas(): void
    {
        $container = new NamespaceContainer(
            __NAMESPACE__,
            self::createMock(InjectorInterface::class),
            fn () => null
        );

        self::assertTrue($container->has(FakeClassNoConstructor::class));
        self::assertFalse($container->has(DateTime::class));
    }

    public function testHas_Root(): void
    {
        $container = new NamespaceContainer(
            '',
            self::createMock(InjectorInterface::class),
            fn () => null
        );

        self::assertTrue($container->has(FakeClassNoConstructor::class));
        self::assertTrue($container->has(DateTime::class));
    }
}
