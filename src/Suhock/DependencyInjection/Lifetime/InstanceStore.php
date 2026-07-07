<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

use function spl_object_id;

/**
 * Holds the instances cached by lifetime strategies on behalf of a resolution root (a container or a scope). Slots are keyed by strategy instance identity, so a strategy shared across multiple registrations shares one
 * slot. Identity-keying is safe because the registration owning a live slot also owns the strategy, keeping it from
 * being collected and its id from being reused; a resolution root evicting a registration must therefore also call
 * {@see remove()}.
 */
final class InstanceStore
{
    /** @var array<int, object> */
    private array $instances = [];

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
     * Discards the instance cached for the given strategy, if any.
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
     * Discards all cached instances.
     */
    public function clear(): void
    {
        $this->instances = [];
    }
}
