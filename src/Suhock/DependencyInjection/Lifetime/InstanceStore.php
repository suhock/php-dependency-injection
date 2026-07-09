<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

use Suhock\DependencyInjection\DisposableInterface;
use Throwable;
use WeakMap;

use function krsort;
use function spl_object_id;

/**
 * Holds the instances cached by lifetime strategies on behalf of a resolution root (a container or a scope). Slots
 * are keyed by strategy instance identity, so a strategy shared across multiple descriptors shares one slot.
 * Identity-keying is safe because the descriptor owning a live slot also owns the strategy, keeping it from being
 * collected and its id from being reused; a resolution root evicting a descriptor must therefore also call
 * {@see remove()}.
 *
 * The store also tracks the container-owned {@see DisposableInterface} instances created within its resolution root so
 * that they can be disposed when the root's lifetime ends. See {@see addDisposable()} and {@see dispose()}.
 */
final class InstanceStore
{
    /** @var array<int, object> */
    private array $instances = [];

    /**
     * Disposable instances awaiting disposal, mapped to their registration sequence number. Held weakly so that an
     * instance dropped before disposal (e.g. a transient no longer referenced by the application) is collected
     * normally and simply omitted from the sweep in {@see dispose()}.
     *
     * @var WeakMap<DisposableInterface, int>
     */
    private WeakMap $disposables;

    private int $nextSequence = 0;

    public function __construct()
    {
        $this->disposables = new WeakMap();
    }

    /**
     * Returns the instance cached for the given strategy, creating and caching it from the factory on first use.
     *
     * @template TClass of object
     *
     * @param LifetimeStrategy<TClass> $strategy The strategy the instance is cached for
     * @param callable():TClass $factory Factory invoked only if no instance is cached yet
     *
     * @return TClass
     */
    public function getOrCreate(LifetimeStrategy $strategy, callable $factory): object
    {
        /** @var TClass */
        return $this->instances[spl_object_id($strategy)] ??= $factory();
    }

    /**
     * Records a container-owned disposable instance to be disposed when this store is disposed. The registry holds only
     * a weak reference to the instance, so an instance that becomes unreachable before disposal is dropped rather than
     * kept alive. Recording an already recorded instance has no effect and preserves its original registration order,
     * so an instance shared by more than one descriptor is disposed at most once per store.
     *
     * @param DisposableInterface $instance The instance to dispose when the store is disposed
     */
    public function addDisposable(DisposableInterface $instance): void
    {
        $this->disposables[$instance] ??= $this->nextSequence++;
    }

    /**
     * Discards the instance cached for the given strategy, if any. Does not dispose the instance; a still-referenced
     * instance recorded via {@see addDisposable()} remains eligible for disposal when the store is disposed.
     *
     * @template TClass of object
     *
     * @param LifetimeStrategy<TClass> $strategy
     */
    public function remove(LifetimeStrategy $strategy): void
    {
        unset($this->instances[spl_object_id($strategy)]);
    }

    /**
     * Discards all cached instances without disposing them. Instances recorded via {@see addDisposable()} remain
     * eligible for disposal when the store is disposed.
     */
    public function clear(): void
    {
        $this->instances = [];
    }

    /**
     * Disposes every recorded disposable instance still alive, in reverse registration order so that dependents are
     * disposed before their dependencies, then discards all cached instances and registrations. If a
     * {@see DisposableInterface::dispose()} call throws, the sweep continues and the first exception is rethrown once
     * the sweep completes; any later exceptions are suppressed. Disposing an already disposed or empty store has no
     * effect.
     *
     * @throws Throwable The first exception thrown by any disposed instance
     */
    public function dispose(): void
    {
        // Snapshot the live registrations, then reset all state before sweeping so that a disposer that calls back into
        // this store (directly or via a reentrant dispose()) sees an already empty store and does nothing.
        $ordered = [];

        foreach ($this->disposables as $instance => $sequence) {
            $ordered[$sequence] = $instance;
        }

        $this->disposables = new WeakMap();
        $this->instances = [];

        krsort($ordered);

        $firstException = null;

        foreach ($ordered as $instance) {
            try {
                $instance->dispose();
            } catch (Throwable $exception) {
                $firstException ??= $exception;
            }
        }

        if ($firstException !== null) {
            throw $firstException;
        }
    }
}
