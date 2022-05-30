<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Lifetime;

/**
 * @template TClass of object
 * @extends LifetimeStrategy<TClass>
 */
class TransientStrategy extends LifetimeStrategy
{
    /**
     * @inheritDoc
     */
    public function get(callable $factory): ?object
    {
        return $factory();
    }
}
