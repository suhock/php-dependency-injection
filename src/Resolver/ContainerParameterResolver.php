<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use ReflectionParameter;
use ReflectionProperty;
use Suhock\DependencyInjection\ClassResolutionException;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\Key;
use UnitEnum;

/**
 * Resolves function parameters and injected properties from a {@see ContainerInterface}, honoring the {@see Key}
 * attribute on a parameter. A keyed injection point is resolved absolutely against that key; an unkeyed one is
 * resolved by type. Only the injection point itself is inspected. No scope is tracked and the declaring function and
 * class are not traversed. Each path builds a single {@see ResolvableDependency} and resolves it through
 * {@see DependencyResolver}, so parameter and property resolution share one algorithm with the container.
 *
 * @internal
 */
final class ContainerParameterResolver implements ParameterResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {}

    public function resolveParameter(ReflectionParameter $rParam): mixed
    {
        $deferredException = null;

        try {
            $dependency = ResolvableDependencyFactory::createFromParameter($rParam);

            if ($dependency !== null) {
                $instance = DependencyResolver::resolve($dependency, $this->container);

                if ($instance !== null) {
                    return $instance;
                }
            }
        } catch (ClassResolutionException $e) {
            $deferredException = $e;
        }

        if ($rParam->isDefaultValueAvailable()) {
            return $rParam->getDefaultValue();
        }

        if ($rParam->allowsNull()) {
            return null;
        }

        throw new ParameterResolutionException($rParam, $deferredException);
    }

    public function resolveProperty(ReflectionProperty $rProperty, string|UnitEnum|null $key): mixed
    {
        $deferredException = null;
        $rType = $rProperty->getType();
        $dependency = ResolvableDependencyFactory::createFromType($rType, $key);

        if ($dependency !== null) {
            try {
                $instance = DependencyResolver::resolve($dependency, $this->container);

                if ($instance !== null) {
                    return $instance;
                }
            } catch (ClassResolutionException $e) {
                $deferredException = $e;
            }
        }

        if ($rType !== null && $rType->allowsNull()) {
            return null;
        }

        throw new PropertyResolutionException($rProperty, $deferredException);
    }
}
