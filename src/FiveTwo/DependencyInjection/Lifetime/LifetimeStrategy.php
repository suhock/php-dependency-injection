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
 * @template TClass of object
 */
abstract class LifetimeStrategy
{
    /**
     * @param class-string<TClass> $className
     */
    public function __construct(
        private readonly string $className
    ) {
    }

    /**
     * @return class-string<TClass>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @param callable $factory
     * @psalm-param callable(mixed ...):(TClass|null) $factory
     * @phpstan-param callable(mixed ...):(TClass|null) $factory
     *
     * @return TClass|null
     */
    abstract public function get(callable $factory): ?object;
}
