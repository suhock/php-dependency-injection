<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeConfigurator;
use Suhock\DependencyInjection\Fakes\FakeUnitEnum;
use Suhock\DependencyInjection\InstanceProvider\ObjectInstanceProvider;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;

/**
 * Test suite for {@see Container}.
 */
final class ContainerTest extends AbstractDependencyInjectionTestCase
{
    protected function createContainer(): Container
    {
        return Container::createDefault();
    }

    public function testAdd_WithValidClass_AddsDescriptor(): void
    {
        // Arrange
        $container = $this->createContainer();
        $lifetimeStrategy = new SingletonStrategy(FakeClassNoConstructor::class);
        $instanceProvider = new ObjectInstanceProvider(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        // Act
        $container->add(FakeClassNoConstructor::class, $lifetimeStrategy, $instanceProvider);

        // Assert
        self::assertTrue($container->has(FakeClassNoConstructor::class));
    }

    public function testAdd_WithDuplicateClass_ThrowsContainerException(): void
    {
        // Arrange
        $container = $this->createContainer();
        $lifetimeStrategy = new SingletonStrategy(FakeClassNoConstructor::class);
        $instanceProvider = new ObjectInstanceProvider(FakeClassNoConstructor::class, new FakeClassNoConstructor());
        $container->add(FakeClassNoConstructor::class, $lifetimeStrategy, $instanceProvider);

        // Act & Assert
        $this->expectException(ContainerException::class);
        $container->add(FakeClassNoConstructor::class, $lifetimeStrategy, $instanceProvider);
    }

    public function testAddKeyed_WithValidClass_AddsKeyedDescriptor(): void
    {
        // Arrange
        $container = $this->createContainer();
        $lifetimeStrategy = new SingletonStrategy(FakeClassNoConstructor::class);
        $instanceProvider = new ObjectInstanceProvider(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        // Act
        $container->addKeyed(FakeClassNoConstructor::class, 'key1', $lifetimeStrategy, $instanceProvider);

        // Assert
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key1'));
    }

    public function testAddKeyed_WithDuplicateKey_ThrowsContainerException(): void
    {
        // Arrange
        $container = $this->createContainer();
        $lifetimeStrategy = new SingletonStrategy(FakeClassNoConstructor::class);
        $instanceProvider = new ObjectInstanceProvider(FakeClassNoConstructor::class, new FakeClassNoConstructor());
        $container->addKeyed(FakeClassNoConstructor::class, 'key1', $lifetimeStrategy, $instanceProvider);

        // Act & Assert
        $this->expectException(ContainerException::class);
        $container->addKeyed(FakeClassNoConstructor::class, 'key1', $lifetimeStrategy, $instanceProvider);
    }

    public function testAddKeyed_WhenUnkeyedServiceExists_AddsIndependentKeyedDescriptor(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor());
        $lifetimeStrategy = new SingletonStrategy(FakeClassNoConstructor::class);
        $instanceProvider = new ObjectInstanceProvider(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        // Act
        $container->addKeyed(FakeClassNoConstructor::class, 'key1', $lifetimeStrategy, $instanceProvider);

        // Assert
        self::assertTrue($container->has(FakeClassNoConstructor::class));
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key1'));
    }

    public function testConfigure_WithCallback_InvokesCallbackWithSelf(): void
    {
        // Arrange
        $container = $this->createContainer();
        $configurator = $this->createMock(FakeConfigurator::class);
        $configurator->expects($this->once())
            ->method('configure')
            ->with($container);

        // Act
        $container->configure($configurator->configure(...));
    }

    public function testRemove_WithExistingClassName_RemovesClassFromContainer(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonClass(FakeClassNoConstructor::class);

        // Act
        $container->remove(FakeClassNoConstructor::class);

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class));
    }

    public function testRemove_WithKey_RemovesOnlyKeyedService(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonClass(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1')
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key2');

        // Act
        $container->remove(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class, 'key1'));
        self::assertTrue($container->has(FakeClassNoConstructor::class));
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key2'));
    }

    public function testRemove_WithoutKey_DoesNotRemoveKeyedService(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonClass(FakeClassNoConstructor::class)
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');

        // Act
        $container->remove(FakeClassNoConstructor::class);

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class));
        self::assertTrue($container->has(FakeClassNoConstructor::class, 'key1'));
    }

    public function testRemove_WithEnumKey_RemovesKeyedService(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, FakeUnitEnum::Test);

        // Act
        $container->remove(FakeClassNoConstructor::class, FakeUnitEnum::Test);

        // Assert
        self::assertFalse($container->has(FakeClassNoConstructor::class, FakeUnitEnum::Test));
    }

    public function testRemove_WithKey_AllowsReAddingUnderSameKey(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1');
        $container->get(FakeClassNoConstructor::class, 'key1');

        // Act
        $container->remove(FakeClassNoConstructor::class, 'key1')
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance);

        // Assert
        self::assertSame($expectedInstance, $container->get(FakeClassNoConstructor::class, 'key1'));
    }

    public function testGet_WhenClassNotInContainer_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testGet_WhenClassHasCircularDependency_ThrowsWrappedCircularDependencyException(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonFactory(FakeClassNoConstructor::class, fn (FakeClassNoConstructor $obj) => $obj);

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassResolutionException(
            FakeClassNoConstructor::class,
            static fn (CircularDependencyException $exception) => self::assertCircularDependencyException(
                FakeClassNoConstructor::class,
                $exception
            ),
            $fn
        );
    }

    public function testGet_WithRegisteredKey_ReturnsKeyedInstance(): void
    {
        // Arrange
        $expectedInstance = new FakeClassNoConstructor();
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', $expectedInstance);

        // Act
        $result = $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertSame($expectedInstance, $result);
    }

    public function testGet_WithUnregisteredKey_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testGet_WithoutKeyWhenOnlyKeyedServiceExists_ThrowsClassNotFoundException(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', new FakeClassNoConstructor());

        // Act
        $fn = static fn () => $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertThrowsClassNotFoundException(FakeClassNoConstructor::class, $fn);
    }

    public function testHas_WhenClassNotInContainer_ReturnsFalse(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertFalse($result);
    }

    public function testHas_WhenValueProvidedByInstance_ReturnsTrue(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertTrue($result);
    }

    public function testHas_WhenValueProvidedByFactory_ReturnsTrue(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonFactory(FakeClassNoConstructor::class, fn () => new FakeClassNoConstructor());

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertTrue($result);
    }

    public function testHas_WithRegisteredKey_ReturnsTrue(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', new FakeClassNoConstructor());

        // Act
        $result = $container->has(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertTrue($result);
    }

    public function testHas_WithUnregisteredKeyWhenUnkeyedServiceExists_ReturnsFalse(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addSingletonInstance(FakeClassNoConstructor::class, new FakeClassNoConstructor());

        // Act
        $result = $container->has(FakeClassNoConstructor::class, 'key1');

        // Assert
        self::assertFalse($result);
    }

    public function testHas_WithoutKeyWhenOnlyKeyedServiceExists_ReturnsFalse(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeClassNoConstructor::class, 'key1', new FakeClassNoConstructor());

        // Act
        $result = $container->has(FakeClassNoConstructor::class);

        // Assert
        self::assertFalse($result);
    }
}
