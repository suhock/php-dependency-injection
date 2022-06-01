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
 * Default implementation for the {@see InjectorInterface} that injects missing parameter values from a
 * {@see ContainerInterface}
 */
class Injector implements InjectorInterface
{
    use InjectorTrait;
    use ContainerInjectorTrait;

    /**
     * @param ContainerInterface $container The container from which to resolve parameter values
     */
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @inheritDoc
     */
    protected function tryResolveParameter(ReflectionParameter $rParam, mixed &$paramValue): bool
    {
        return $this->getInstanceFromParameter($rParam, $paramValue);
    }
}
