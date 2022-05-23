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
class SingletonStrategy extends LifetimeStrategy
{
    /** @var TDependency|null */
    private ?object $instance = null;

    private bool $isSet = false;

    /**
     * @param callable():TDependency $factory
     *
     * @return TDependency|null
     * @psalm-param callable():(TDependency|null) $factory
     */
    public function get(callable $factory): ?object
    {
        if (!$this->isSet) {
            $this->instance = $factory();
            $this->isSet = true;
        }

        return $this->instance;
    }
}
