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
use Suhock\DependencyInjection\InstanceProvider\ClassInstanceProvider;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use function gc_collect_cycles;

/**
 * Test suite for {@see Container::dispose()} and container-owned disposal.
 */
final class ContainerDisposeTest extends AbstractDependencyInjectionTestCase
{
    private function createContainer(): Container
    {
        return Container::createDefault();
    }

    public function testContainer_ImplementsDisposableInterface(): void
    {
        // Arrange & Act
        $container = $this->createContainer();

        // Assert
        self::assertInstanceOf(DisposableInterface::class, $container);
    }

    public function testDispose_WithSingletonDisposable_DisposesInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingleton(FakeDisposableClass::class);
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
        $container = $this->createContainer()
            ->addSingletonInstance(FakeDisposalLog::class, $log)
            ->addSingleton(FakeDisposableClass::class)
            ->addSingleton(FakeDisposableClassWithDependency::class);
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
        $container = $this->createContainer()->addSingletonInstance(FakeDisposableClass::class, $instance);
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
        $container = $this->createContainer()
            ->addSingletonInstance(FakeDisposableClass::class, $instance, shouldDispose: false);
        $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        self::assertSame(0, $instance->disposeCount);
    }

    public function testDispose_WithShouldDisposeFalseSingleton_DoesNotDisposeInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->add(
            FakeDisposableClass::class,
            new SingletonStrategy(FakeDisposableClass::class),
            new ClassInstanceProvider(FakeDisposableClass::class),
            shouldDispose: false
        );
        $instance = $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        self::assertSame(0, $instance->disposeCount);
    }

    public function testDispose_WithKeyedSingletonDisposables_DisposesEachInstance(): void
    {
        // Arrange
        $container = $this->createContainer()
            ->addKeyedSingleton(FakeDisposableClass::class, 'first')
            ->addKeyedSingleton(FakeDisposableClass::class, 'second');
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
        $container = $this->createContainer()->addTransient(FakeDisposableClass::class);
        $instance = $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_WithNestedContainerProvidedDisposable_DisposesInstance(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingletonNamespace('Suhock\DependencyInjection\Fakes');
        $instance = $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_ThenGet_ThrowsContainerException(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingleton(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        $this->expectException(ContainerException::class);
        $container->get(FakeDisposableClass::class);
    }

    public function testDispose_ThenHas_ThrowsContainerException(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingleton(FakeDisposableClass::class);

        // Act
        $container->dispose();

        // Assert
        $this->expectException(ContainerException::class);
        $container->has(FakeDisposableClass::class);
    }

    public function testDispose_ThenCreateScope_ThrowsContainerException(): void
    {
        // Arrange
        $container = $this->createContainer();

        // Act
        $container->dispose();

        // Assert
        $this->expectException(ContainerException::class);
        $container->createScope();
    }

    public function testDispose_ThenGetFromLiveScope_ThrowsContainerException(): void
    {
        // Arrange
        $container = $this->createContainer()->addScoped(FakeDisposableClass::class);
        $scope = $container->createScope();

        // Act
        $container->dispose();

        // Assert
        $this->expectException(ContainerException::class);
        $scope->get(FakeDisposableClass::class);
    }

    public function testDispose_ThenScopeDispose_DisposesScopeInstances(): void
    {
        // Arrange
        $container = $this->createContainer()->addScoped(FakeDisposableClass::class);
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
        $container = $this->createContainer()->addSingleton(FakeDisposableClass::class);
        $instance = $container->get(FakeDisposableClass::class);

        // Act
        $container->dispose();
        $container->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_AfterRemove_DisposesRemovedInstanceStillReferenced(): void
    {
        // Arrange
        $container = $this->createContainer()->addSingleton(FakeDisposableClass::class);
        $instance = $container->get(FakeDisposableClass::class);

        // Act
        $container->remove(FakeDisposableClass::class);
        $container->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_AfterRemove_DoesNotDisposeCollectedInstance(): void
    {
        // Arrange
        $log = new FakeDisposalLog();
        $container = $this->createContainer()
            ->addSingletonInstance(FakeDisposalLog::class, $log)
            ->addSingleton(FakeDisposableClass::class);
        $instance = $container->get(FakeDisposableClass::class);

        // Act
        $container->remove(FakeDisposableClass::class);
        unset($instance);
        gc_collect_cycles();
        $container->dispose();

        // Assert
        self::assertSame([], $log->entries);
    }
}
