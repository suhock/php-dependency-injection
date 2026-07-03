<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Fakes\FakeBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassExtendsBaseClass;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithConstructor;
use Suhock\DependencyInjection\Fakes\FakeClassWithDependencies;
use Suhock\DependencyInjection\Fakes\FakeInterfaceOne;

/**
 * Test suite for {@see InterfaceContainer}.
 */
final class InterfaceContainerTest extends AbstractDependencyInjectionTestCase
{
    public function testGet_WithDefaultInjectorAndDefaultFactory_ReturnsInstance(): void
    {
        // Arrange
        $container = new InterfaceContainer(FakeBaseClass::class);

        // Act
        $instance = $container->get(FakeClassExtendsBaseClass::class);

        // Assert
        self::assertInstanceOf(FakeClassExtendsBaseClass::class, $instance);
    }

    public function testGet_WithExplicitInjectorAndExplicitFactory_UsesInjectorAndFactory(): void
    {
        // Arrange
        $container = $this->createMock(ContainerInterface::class);
        $container->expects($this->once())
            ->method('get')
            ->with(FakeClassNoConstructor::class)
            ->willReturn(new FakeClassNoConstructor());
        $container->method('has')
            ->willReturn(true);

        $implContainer = new InterfaceContainer(
            FakeInterfaceOne::class,
            Injector::createDefault($container),
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
        $fn = static fn () => $container->get(FakeClassWithDependencies::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassWithDependencies::class, $fn);
    }

    public function testHas_WithSubclassOfInterface_ReturnsTrue(): void
    {
        // Arrange
        $container = new InterfaceContainer(FakeBaseClass::class);

        // Act
        $result = $container->has(FakeClassExtendsBaseClass::class);

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
        $result = $container->has(FakeClassWithDependencies::class);

        // Assert
        self::assertFalse($result);
    }
}
