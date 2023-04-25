<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Closure;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * Holds information about containers nested inside {@see Container}.
 *
 * @internal
 */
class ContainerDescriptor
{
    /**
     * @param ContainerInterface $container
     * @param InjectorInterface $injector
     * @param Closure(class-string):LifetimeStrategy $lifetimeStrategyFactory
     *
     * @psalm-mutation-free
     * @phpstan-ignore-next-line PHPStan does not support callable-level generics but complains that LifetimeStrategy
     * does not have its generic type specified
     */
    public function __construct(
        public readonly ContainerInterface $container,
        public readonly InjectorInterface $injector,
        public readonly Closure $lifetimeStrategyFactory
    ) {
    }
}
