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

/**
 * Test suite for the container's automatic self-bindings: {@see ContainerInterface} resolves to the current
 * resolution root and {@see ScopeFactoryInterface} to the root container, unless the configuration provides its own.
 */
final class AutoBindingTest extends AbstractDependencyInjectionTestCase
{
    public function testHas_OnBareProduct_ReportsTheAutoBoundServices(): void
    {
        // Arrange
        $container = self::buildContainer(static fn (ContainerBuilder $builder) => null);

        // Assert
        self::assertTrue($container->has(ContainerInterface::class));
        self::assertTrue($container->has(ScopeFactoryInterface::class));
    }

    public function testGet_ContainerInterfaceFromRoot_ReturnsTheRootContainer(): void
    {
        // Arrange
        $container = self::buildContainer(static fn (ContainerBuilder $builder) => null);

        // Act & Assert
        self::assertSame($container, $container->get(ContainerInterface::class));
    }

    public function testGet_SingletonDependingOnContainerInterface_ReceivesTheRootContainer(): void
    {
        // Arrange
        $received = null;
        $container = self::buildContainer(
            static function (ContainerBuilder $builder) use (&$received): void {
                $builder->addSingletonFactory(
                    FakeClassNoConstructor::class,
                    static function (ContainerInterface $c) use (&$received): FakeClassNoConstructor {
                        $received = $c;

                        return new FakeClassNoConstructor();
                    }
                );
            }
        );

        // Act
        $container->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($container, $received);
    }

    public function testGet_ScopedServiceDependingOnContainerInterface_ReceivesItsScope(): void
    {
        // Arrange
        $received = null;
        $container = self::buildContainer(
            static function (ContainerBuilder $builder) use (&$received): void {
                $builder->addScopedFactory(
                    FakeClassNoConstructor::class,
                    static function (ContainerInterface $c) use (&$received): FakeClassNoConstructor {
                        $received = $c;

                        return new FakeClassNoConstructor();
                    }
                );
            }
        );
        $scope = $container->createScope();

        // Act
        $scope->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($scope, $received);
    }

    public function testGet_ScopeFactoryInterfaceFromScope_ReturnsTheRootContainer(): void
    {
        // Arrange
        $received = null;
        $container = self::buildContainer(
            static function (ContainerBuilder $builder) use (&$received): void {
                $builder->addScopedFactory(
                    FakeClassNoConstructor::class,
                    static function (ScopeFactoryInterface $factory) use (&$received): FakeClassNoConstructor {
                        $received = $factory;

                        return new FakeClassNoConstructor();
                    }
                );
            }
        );
        $scope = $container->createScope();

        // Act
        $scope->get(FakeClassNoConstructor::class);

        // Assert
        self::assertSame($container, $received);
    }

    public function testGet_WithUserProvidedContainerInterfaceBinding_PrefersTheUserBinding(): void
    {
        // Arrange
        $userSupplied = self::buildContainer(static fn (ContainerBuilder $builder) => null);
        $container = self::buildContainer(
            static fn (ContainerBuilder $builder) =>
                $builder->addSingletonInstance(ContainerInterface::class, $userSupplied)
        );

        // Act & Assert
        self::assertSame($userSupplied, $container->get(ContainerInterface::class));
    }

    public function testDispose_AfterResolvingAutoBoundServices_DoesNotSelfDispose(): void
    {
        // Arrange
        $container = self::buildContainer(static fn (ContainerBuilder $builder) => null);
        $container->get(ContainerInterface::class);
        $container->get(ScopeFactoryInterface::class);

        // Act & Assert: disposal completes without the container sweeping itself.
        $container->dispose();
        $this->expectException(ContainerException::class);
        $container->get(ContainerInterface::class);
    }
}
