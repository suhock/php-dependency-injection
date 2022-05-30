<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
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
