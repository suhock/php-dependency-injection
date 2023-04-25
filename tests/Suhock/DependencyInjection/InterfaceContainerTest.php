<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

/**
 * Test suite for {@see InterfaceContainer}.
 */
class InterfaceContainerTest extends DependencyInjectionTestCase
{
    public function testGet_WithDefaultInjectorAndDefaultFactory_ReturnsInstance(): void
    {
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        self::assertInstanceOf(
            FakeClassExtendsNoConstructor::class,
            $container->get(FakeClassExtendsNoConstructor::class)
        );
    }

    public function testGet_WithExplicitInjectorAndExplicitFactory_UsesInjectorAndFactory(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())
            ->method('get')
            ->with(FakeClassNoConstructor::class)
            ->willReturn(new FakeClassNoConstructor());
        $container->method('has')
            ->willReturn(true);

        $implContainer = new InterfaceContainer(
            FakeInterfaceOne::class,
            new ContainerInjector($container),
            fn (string $className, FakeClassNoConstructor $obj) => new FakeClassWithConstructor($obj)
        );

        self::assertInstanceOf(
            FakeClassWithConstructor::class,
            $implContainer->get(FakeClassWithConstructor::class)
        );
    }

    public function testGet_WithImplementationClassSameAsInterface_ThrowsClassNotFoundException(): void
    {
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        self::assertThrowsClassNotFoundException(
            FakeClassNoConstructor::class,
            static fn () => $container->get(FakeClassNoConstructor::class)
        );
    }

    public function testGet_WithImplementationClassNotInstanceOfInterface_ThrowsClassNotFoundException(): void
    {
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        self::assertThrowsClassNotFoundException(
            FakeClassWithContexts::class,
            static fn () => $container->get(FakeClassWithContexts::class)
        );
    }

    public function testHas_WithSubclassOfInterface_ReturnsTrue(): void
    {
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        self::assertTrue($container->has(FakeClassExtendsNoConstructor::class));
    }

    public function testHas_WithSameClassAsInterface_ReturnsFalse(): void
    {
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        self::assertFalse($container->has(FakeClassNoConstructor::class));
    }

    public function testHas_WithImplementationNotSubclassOfInterface_ReturnsFalse(): void
    {
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        self::assertFalse($container->has(FakeClassWithContexts::class));
    }
}
