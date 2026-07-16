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
use Suhock\DependencyInjection\ClassResolutionException;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\Key;

/**
 * Resolves a function parameter from a {@see ContainerInterface}, honoring the {@see Key} attribute on the parameter.
 * A keyed parameter is resolved absolutely against that key; an unkeyed one is resolved by type. Only the parameter
 * itself is inspected. The parameter's type maps to a single {@see ResolvableDependency}, resolved through
 * {@see DependencyResolver}.
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
}
