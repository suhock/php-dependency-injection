<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use ReflectionParameter;

/**
 * Provides a default implementation for the {@see InjectorInterface} that injects parameters resolved from
 * {@see $container}.
 */
class Injector implements InjectorInterface
{
    use InjectorTrait;

    /**
     * @param ContainerInterface $container The container from which to resolve parameter values.
     */
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * @inheritDoc
     */
    protected function tryResolveParameter(ReflectionParameter $rParam, mixed &$paramValue): bool
    {
        return InjectorHelper::getInstanceFromParameter($this->container, $rParam, $paramValue);
    }
}
