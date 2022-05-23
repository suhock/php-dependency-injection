<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Lifetime;

/**
 * @template TDependency
 * @extends LifetimeStrategy<TDependency>
 */
class TransientStrategy extends LifetimeStrategy
{
    /**
     * @param callable():TDependency $factory
     *
     * @return TDependency|null
     * @psalm-param callable():(TDependency|null) $factory
     */
    public function get(callable $factory): ?object
    {
        return $factory();
    }
}
