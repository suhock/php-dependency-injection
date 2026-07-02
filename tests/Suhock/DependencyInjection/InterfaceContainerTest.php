<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
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
        // Arrange
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        // Act
        $instance = $container->get(FakeClassExtendsNoConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsNoConstructor::class, $instance);
    }

    public function testGet_WithExplicitInjectorAndExplicitFactory_UsesInjectorAndFactory(): void
    {
        // Arrange
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

        // Act
        $instance = $implContainer->get(FakeClassWithConstructor::class);

        // Assert
        self::assertInstanceOf(FakeClassWithConstructor::class, $instance);
    }

    public function testGet_WithImplementationClassSameAsInterface_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testGet_WithImplementationClassNotInstanceOfInterface_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        // Act
        $fn = static fn () => $container->get(FakeClassWithContexts::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassWithContexts::class, $fn);
    }

    public function testHas_WithSubclassOfInterface_ReturnsTrue(): void
    {
        // Arrange
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        // Act
        $result = $container->has(FakeClassExtendsNoConstructor::class);

        // Assert
        self::assertTrue($result);
    }

    public function testHas_WithSameClassAsInterface_ReturnsFalse(): void
    {
        // Arrange
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertFalse($result);
    }

    public function testHas_WithImplementationNotSubclassOfInterface_ReturnsFalse(): void
    {
        // Arrange
        $container = new InterfaceContainer(FakeClassNoConstructor::class);

        // Act
        $result = $container->has(FakeClassWithContexts::class);

        // Assert
        self::assertFalse($result);
    }
}
