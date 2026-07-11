<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Suhock\DependencyInjection\DisposableInterface;
use Suhock\DependencyInjection\Fakes\FakeClassNoConstructor;
use Suhock\DependencyInjection\Fakes\FakeDisposableClass;
use Suhock\DependencyInjection\Fakes\FakeDisposalLog;

use function gc_collect_cycles;

/**
 * Test suite for {@see InstanceStore}, focused on the disposal registry.
 */
final class InstanceStoreTest extends TestCase
{
    /**
     * @return SingletonStrategy<FakeClassNoConstructor>
     */
    private function createStrategy(): SingletonStrategy
    {
        return new SingletonStrategy(FakeClassNoConstructor::class);
    }

    public function testClear_DiscardsCachedInstances(): void
    {
        // Arrange
        $store = new InstanceStore();
        $strategy = $this->createStrategy();
        $first = $store->getOrCreate($strategy, fn () => new FakeClassNoConstructor());

        // Act
        $store->clear();
        $second = $store->getOrCreate($strategy, fn () => new FakeClassNoConstructor());

        // Assert
        self::assertNotSame($first, $second);
    }

    public function testDispose_WithRegisteredDisposables_DisposesInReverseRegistrationOrder(): void
    {
        // Arrange
        $log = new FakeDisposalLog();
        $store = new InstanceStore();
        // The store holds disposables only weakly, so the test keeps them alive, as the container's cache would.
        $first = new FakeDisposableClass($log, 'first');
        $second = new FakeDisposableClass($log, 'second');
        $third = new FakeDisposableClass($log, 'third');
        $store->addDisposable($first);
        $store->addDisposable($second);
        $store->addDisposable($third);

        // Act
        $store->dispose();

        // Assert
        self::assertSame(['third', 'second', 'first'], $log->entries);
    }

    public function testDispose_WhenInstanceGarbageCollected_SkipsInstance(): void
    {
        // Arrange
        $log = new FakeDisposalLog();
        $store = new InstanceStore();
        $collected = new FakeDisposableClass($log, 'collected');
        $retained = new FakeDisposableClass($log, 'retained');
        $store->addDisposable($collected);
        $store->addDisposable($retained);

        // Act
        unset($collected);
        gc_collect_cycles();
        $store->dispose();

        // Assert
        self::assertSame(['retained'], $log->entries);
    }

    public function testDispose_WhenDisposeThrows_ContinuesSweepAndRethrowsFirstException(): void
    {
        // Arrange
        $log = new FakeDisposalLog();
        $store = new InstanceStore();
        $first = new FakeDisposableClass($log, 'first');
        $throwing = new class ($log) implements DisposableInterface {
            public function __construct(private readonly FakeDisposalLog $log)
            {
            }

            public function dispose(): void
            {
                $this->log->record('throwing');

                throw new RuntimeException('dispose failed');
            }
        };
        $third = new FakeDisposableClass($log, 'third');
        $store->addDisposable($first);
        $store->addDisposable($throwing);
        $store->addDisposable($third);

        // Act
        $exception = null;

        try {
            $store->dispose();
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        // Assert
        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('dispose failed', $exception->getMessage());
        self::assertSame(['third', 'throwing', 'first'], $log->entries);
    }

    public function testDispose_CalledTwice_DisposesInstancesOnlyOnce(): void
    {
        // Arrange
        $store = new InstanceStore();
        $instance = new FakeDisposableClass();
        $store->addDisposable($instance);

        // Act
        $store->dispose();
        $store->dispose();

        // Assert
        self::assertSame(1, $instance->disposeCount);
    }

    public function testDispose_ClearsCachedInstances(): void
    {
        // Arrange
        $strategy = $this->createStrategy();
        $store = new InstanceStore();
        $callCount = 0;
        $factory = static function () use (&$callCount): FakeClassNoConstructor {
            ++$callCount;

            return new FakeClassNoConstructor();
        };
        $store->getOrCreate($strategy, $factory);

        // Act
        $store->dispose();
        $store->getOrCreate($strategy, $factory);

        // Assert
        self::assertSame(2, $callCount);
    }

    public function testAddDisposable_WithSameInstanceTwice_DisposesOnceInOriginalOrder(): void
    {
        // Arrange
        $log = new FakeDisposalLog();
        $store = new InstanceStore();
        $shared = new FakeDisposableClass($log, 'shared');
        $later = new FakeDisposableClass($log, 'later');
        $store->addDisposable($shared);
        $store->addDisposable($later);
        $store->addDisposable($shared);

        // Act
        $store->dispose();

        // Assert
        self::assertSame(1, $shared->disposeCount);
        self::assertSame(['later', 'shared'], $log->entries);
    }

    public function testRemove_DoesNotDisposeRegisteredInstance(): void
    {
        // Arrange
        $strategy = new SingletonStrategy(FakeDisposableClass::class);
        $store = new InstanceStore();
        $instance = new FakeDisposableClass();
        $store->getOrCreate($strategy, static fn () => $instance);
        $store->addDisposable($instance);

        // Act
        $store->remove($strategy);

        // Assert
        self::assertSame(0, $instance->disposeCount);
    }
}
