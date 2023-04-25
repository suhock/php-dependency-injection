<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

/**
 * Manages the lifetime of a singleton object.
 *
 * @template TClass of object
 * @extends LifetimeStrategy<TClass>
 */
class SingletonStrategy extends LifetimeStrategy
{
    /** @var TClass */
    private ?object $instance = null;

    /**
     * @inheritDoc
     */
    public function get(callable $factory): object
    {
        return $this->instance ??= $factory();
    }
}
