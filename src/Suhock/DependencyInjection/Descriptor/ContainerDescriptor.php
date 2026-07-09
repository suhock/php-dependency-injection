<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Descriptor;

use Closure;
use Suhock\DependencyInjection\Container;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * Holds information about containers nested inside {@see Container}.
 *
 * @internal
 */
final class ContainerDescriptor
{
    /**
     * @param Closure(class-string):LifetimeStrategy<object> $lifetimeStrategyFactory
     */
    public function __construct(
        public readonly ContainerInterface $container,
        public readonly Closure $lifetimeStrategyFactory
    ) {
    }
}
