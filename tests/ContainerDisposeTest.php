<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Fakes\FakeDisposableClass;
use Suhock\DependencyInjection\Fakes\FakeDisposableClassWithDependency;
use Suhock\DependencyInjection\Fakes\FakeDisposalLog;
use Suhock\Disposable\DisposableInterface;

/**
 * Test suite for {@see Container::dispose()} and container-owned disposal.
 */
final class ContainerDisposeTest extends AbstractDependencyInjectionTestCase
{
    public function testContainer_ImplementsDisposableInterface(): void
    {
        // Arrange & Act
        $container = self::buildContainer(static fn(ContainerBuilder $builder) => null);

        // Assert
        self::assertInstanceOf(DisposableInterface::class, $container);
    }

    public function testDispose_WithSingletonDisposable_DisposesInstance(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeDisposableClass::class),
        );
        $instance = $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_WithDisposableDependencyGraph_DisposesDependentsBeforeDependencies(): void
    {
        // Arrange
        $log = new FakeDisposalLog();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder
                ->addSingletonInstance(FakeDisposalLog::class, $log)
                ->addSingleton(FakeDisposableClass::class)
                ->addSingleton(FakeDisposableClassWithDependency::class),
        );
        $container->get(FakeDisposableClassWithDependency::class);

        // Act
        $container->dispose();

        // Assert
        self::assertSame(['dependent', 'dependency'], $log->entries);
    }

    public function testDispose_WithSingletonInstance_DisposesInstanceByDefault(): void
    {
        // Arrange
        $instance = new FakeDisposableClass();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingletonInstance(FakeDisposableClass::class, $instance),
        );
        $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_WithSingletonInstanceShouldDisposeFalse_DoesNotDisposeInstance(): void
    {
        // Arrange
        $instance = new FakeDisposableClass();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder)
                => $builder->addSingletonInstance(FakeDisposableClass::class, $instance, shouldDispose: false),
        );
        $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        self::assertSame(0, $instance->disposeCount);
    }

    public function testDispose_WithShouldDisposeFalseSingleton_DoesNotDisposeInstance(): void
    {
        // Arrange
        $container = self::buildRawContainer([
            FakeDisposableClass::class => self::classDescriptor(
                FakeDisposableClass::class,
                'singleton',
                shouldDispose: false,
            ),
        ]);
        $instance = $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        self::assertSame(0, $instance->disposeCount);
    }

    public function testDispose_WithKeyedSingletonDisposables_DisposesEachInstance(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder
                ->addKeyedSingleton(FakeDisposableClass::class, 'first')
                ->addKeyedSingleton(FakeDisposableClass::class, 'second'),
        );
        $first = $container->get(FakeDisposableClass::class, 'first');
        $second = $container->get(FakeDisposableClass::class, 'second');

        // Act
        $container->dispose();

        // Assert
        self::assertNotSame($first, $second);
        self::assertSame(1, $first->disposeCount);
        self::assertSame(1, $second->disposeCount);
    }

    public function testDispose_WithRootResolvedTransientDisposable_DisposesSurvivingInstance(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addTransient(FakeDisposableClass::class),
        );
        $instance = $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_ThenGet_ThrowsContainerDisposedException(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeDisposableClass::class),
        );

        // Act
        $container->dispose();

        // Assert
        $this->expectException(ContainerDisposedException::class);
        $container->get(FakeDisposableClass::class);
    }

    public function testDispose_ThenHas_ThrowsContainerDisposedException(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeDisposableClass::class),
        );

        // Act
        $container->dispose();

        // Assert
        $this->expectException(ContainerDisposedException::class);
        $container->has(FakeDisposableClass::class);
    }

    public function testDispose_ThenCreateScope_ThrowsContainerDisposedException(): void
    {
        // Arrange
        $container = self::buildContainer(static fn(ContainerBuilder $builder) => null);

        // Act
        $container->dispose();

        // Assert
        $this->expectException(ContainerDisposedException::class);
        $container->createScope();
    }

    public function testDispose_ThenGetFromLiveScope_ThrowsContainerDisposedException(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeDisposableClass::class),
        );
        $scope = $container->createScope();

        // Act
        $container->dispose();

        // Assert
        $this->expectException(ContainerDisposedException::class);
        $scope->get(FakeDisposableClass::class);
    }

    public function testDispose_ThenScopeDispose_DisposesScopeInstances(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addScoped(FakeDisposableClass::class),
        );
        $scope = $container->createScope();
        $instance = $scope->get(FakeDisposableClass::class);

        // Act
        $container->dispose();
        $scope->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_CalledTwice_DisposesInstancesOnlyOnce(): void
    {
        // Arrange
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeDisposableClass::class),
        );
        $instance = $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();
        $container->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }
}
