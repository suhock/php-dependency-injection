<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use ReflectionNamedType;
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
        if ($rParam->getType() instanceof ReflectionNamedType &&
            !$rParam->getType()->isBuiltin() &&
            $this->container->has($rParam->getType()->getName())) {
            $paramValue = $this->container->get($rParam->getType()->getName());

            return true;
        }

        return false;
    }
}
