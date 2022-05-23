<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Lifetime;

/**
 * @template TDependency
 */
abstract class LifetimeStrategy
{
    /**
     * @param class-string<TDependency> $className
     */
    public function __construct(
        private readonly string $className
    ) {
    }

    /**
     * @return class-string<TDependency>
     */
    public function getClassName(): string
    {
        return $this->className;
    }

    /**
     * @param callable():TDependency $factory
     *
     * @return TDependency|null
     * @psalm-param callable():(TDependency|null) $factory
     */
    public abstract function get(callable $factory): ?object;
}
