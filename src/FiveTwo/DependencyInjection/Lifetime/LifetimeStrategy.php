<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Lifetime;

/**
 * Interface for classes that manage the lifetime of an object instance.
 *
 * @template TClass of object
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
     * @param callable $factory A factory function that should be called when an instance of the class is needed
     * @psalm-param callable(mixed ...):(TClass|null) $factory
     * @phpstan-param callable(mixed ...):(TClass|null) $factory
     *
     * @return TClass|null An instance of the class, or <code>null</code>
     */
    abstract public function get(callable $factory): ?object;
}
