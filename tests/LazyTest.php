<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Fakes\FakeDisposalLog;
use Suhock\DependencyInjection\Fakes\FakeLazyConsumer;
use Suhock\DependencyInjection\Fakes\FakeLazyCounter;
use Suhock\DependencyInjection\Fakes\FakeLazyCycleA;
use Suhock\DependencyInjection\Fakes\FakeLazyCycleB;
use Suhock\DependencyInjection\Fakes\FakeLazyDisposable;
use Suhock\DependencyInjection\Fakes\FakeLazyDisposableConsumer;
use Suhock\DependencyInjection\Fakes\FakeLazyInterface;
use Suhock\DependencyInjection\Fakes\FakeLazyInterfaceConsumer;
use Suhock\DependencyInjection\Fakes\FakeLazyService;

/**
 * Behavior tests for the #[Lazy] attribute in the compiled container: a lazy dependency is not constructed until
 * first used, is constructed once, breaks construction cycles, and preserves the target's lifetime and identity.
 */
final class LazyTest extends AbstractDependencyInjectionTestCase
{
    public function testGet_WithLazyDependency_DoesNotConstructUntilFirstUse(): void
    {
        // Arrange
        $counter = new FakeLazyCounter();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeLazyCounter::class, $counter)
                ->addTransient(FakeLazyService::class)
                ->addTransient(FakeLazyConsumer::class),
        );

        // Act
        $consumer = $container->get(FakeLazyConsumer::class);

        // Assert
        self::assertInstanceOf(FakeLazyService::class, $consumer->service);
        self::assertSame(0, $counter->constructions);
    }

    public function testGet_WithLazyDependency_ConstructsOnceOnFirstUse(): void
    {
        // Arrange
        $counter = new FakeLazyCounter();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeLazyCounter::class, $counter)
                ->addTransient(FakeLazyService::class)
                ->addTransient(FakeLazyConsumer::class),
        );
        $consumer = $container->get(FakeLazyConsumer::class);

        // Act
        $first = $consumer->service->ping();
        $second = $consumer->service->ping();

        // Assert
        self::assertSame('pong', $first);
        self::assertSame('pong', $second);
        self::assertSame(1, $counter->constructions);
    }

    public function testGet_WithLazyInterfaceDependency_DefersAutowiredImplementation(): void
    {
        // Arrange
        $counter = new FakeLazyCounter();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeLazyCounter::class, $counter)
                ->addTransient(FakeLazyService::class)
                ->addTransient(FakeLazyInterface::class, FakeLazyService::class)
                ->addTransient(FakeLazyInterfaceConsumer::class),
        );

        // Act
        $consumer = $container->get(FakeLazyInterfaceConsumer::class);

        // Assert
        self::assertInstanceOf(FakeLazyInterface::class, $consumer->service);
        self::assertSame(0, $counter->constructions);
        self::assertSame('pong', $consumer->service->ping());
        self::assertSame(1, $counter->constructions);
    }

    public function testGet_WithLazyFactoryDependency_DefersProxyUntilUsed(): void
    {
        // Arrange
        $counter = new FakeLazyCounter();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeLazyCounter::class, $counter)
                ->addTransient(
                    FakeLazyService::class,
                    static fn(FakeLazyCounter $c): FakeLazyService => new FakeLazyService($c),
                )
                ->addTransient(FakeLazyConsumer::class),
        );

        // Act
        $consumer = $container->get(FakeLazyConsumer::class);

        // Assert
        self::assertInstanceOf(FakeLazyService::class, $consumer->service);
        self::assertSame(0, $counter->constructions);
        self::assertSame('pong', $consumer->service->ping());
        self::assertSame(1, $counter->constructions);
    }

    public function testGet_WithLazyEdgeOnDependencyCycle_ResolvesWithoutError(): void
    {
        // Arrange: A depends on B lazily, B depends on A eagerly; the lazy edge breaks the otherwise-fatal cycle.
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeLazyCycleA::class)
                ->addSingleton(FakeLazyCycleB::class),
        );

        // Act
        $a = $container->get(FakeLazyCycleA::class);

        // Assert: touching the lazy dependency constructs B, which resolves back to the same singleton A.
        self::assertInstanceOf(FakeLazyCycleB::class, $a->b);
        self::assertSame($a, $a->b->a);
    }

    public function testGet_WithLazySingleton_IsTheSameInstanceForEveryConsumer(): void
    {
        // Arrange
        $counter = new FakeLazyCounter();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeLazyCounter::class, $counter)
                ->addSingleton(FakeLazyService::class)
                ->addTransient(FakeLazyConsumer::class),
        );

        // Act
        $first = $container->get(FakeLazyConsumer::class);
        $second = $container->get(FakeLazyConsumer::class);

        // Assert
        self::assertNotSame($first, $second);
        self::assertSame($first->service, $second->service);
        self::assertSame($first->service, $container->get(FakeLazyService::class));
    }

    public function testDispose_WithUntouchedLazyDisposable_NeitherConstructsNorDisposesIt(): void
    {
        // Arrange
        $log = new FakeDisposalLog();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeDisposalLog::class, $log)
                ->addSingleton(FakeLazyDisposable::class)
                ->addTransient(FakeLazyDisposableConsumer::class),
        );
        $container->get(FakeLazyDisposableConsumer::class);

        // Act
        $container->dispose();

        // Assert: a never-used lazy instance was never constructed, so disposal has nothing to do.
        self::assertSame([], $log->entries);
    }

    public function testDispose_WithUsedLazyDisposable_ConstructsThenDisposesIt(): void
    {
        // Arrange
        $log = new FakeDisposalLog();
        $container = self::buildContainer(
            static fn(ContainerBuilder $builder) => $builder->addSingleton(FakeDisposalLog::class, $log)
                ->addSingleton(FakeLazyDisposable::class)
                ->addTransient(FakeLazyDisposableConsumer::class),
        );
        $consumer = $container->get(FakeLazyDisposableConsumer::class);
        self::assertSame('pong', $consumer->disposable->ping());

        // Act
        $container->dispose();

        // Assert
        self::assertSame(['constructed', 'disposed'], $log->entries);
    }
}
