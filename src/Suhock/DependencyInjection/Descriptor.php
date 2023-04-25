<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022-2023 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use Suhock\DependencyInjection\Provision\InstanceProvider;

/**
 * Contains information about how to resolve a dependency.
 *
 * @template TClass as object
 * @internal
 */
class Descriptor
{
    public bool $isResolving = false;

    /**
     * @param class-string<TClass> $className
     * @param LifetimeStrategy<TClass> $lifetimeStrategy
     * @param InstanceProvider<TClass> $instanceProvider
     * @psalm-mutation-free
     */
    public function __construct(
        public readonly string $className,
        public readonly LifetimeStrategy $lifetimeStrategy,
        public readonly InstanceProvider $instanceProvider
    ) {
    }
}
