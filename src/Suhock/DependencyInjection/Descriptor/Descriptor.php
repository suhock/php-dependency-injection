<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Descriptor;

use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * Contains information about how to resolve a service. A descriptor must remain immutable so that it can be shared by
 * multiple resolution roots.
 *
 * @template TClass as object
 * @internal
 */
final class Descriptor
{
    /**
     * @param class-string<TClass> $className
     * @param LifetimeStrategy<TClass> $lifetimeStrategy
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     * service when the resolution root that cached them is disposed
     */
    public function __construct(
        public readonly string $className,
        public readonly LifetimeStrategy $lifetimeStrategy,
        public readonly InstanceProviderInterface $instanceProvider,
        public readonly bool $shouldDispose = true
    ) {
    }
}
