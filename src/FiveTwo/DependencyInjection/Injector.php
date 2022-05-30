<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
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
        $className = InjectorHelper::getClassNameFromParameter($rParam);

        if ($className !== null && $this->container->has($className)) {
            $paramValue = $this->container->get($className);

            return true;
        }

        return false;
    }
}
