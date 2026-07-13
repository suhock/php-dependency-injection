<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

use Suhock\DependencyInjection\ResolutionContext;

/**
 * Interface for classes that manage the lifetime of an object instance.
 *
 * @template TClass of object
 *
 * @internal The set of lifetime strategies is closed (singleton, scoped, transient); captive-dependency validation depends on classifying every strategy.
 */
abstract class LifetimeStrategy
{
    /**
     * @param class-string<TClass> $className
     */
    public function __construct(
        protected readonly string $className
    ) {
    }

    /**
     * Returns an instance of this strategy's class by invoking the given factory, based on the strategy's rules.
     *
     * A strategy that caches instances must invoke the factory with the same context whose store it caches in, so that
     * the instance's dependencies are resolved from the root the instance's lifetime is bound to.
     *
     * @param ResolutionContext $context The context of the resolution root requesting the instance
     * @param callable $factory A factory function that should be called when an instance of the class is needed,
     * resolving the instance's dependencies from the context it is given
     * @phpstan-param callable(ResolutionContext):TClass $factory
     *
     * @return TClass An instance of the class
     */
    abstract public function get(ResolutionContext $context, callable $factory): object;
}
