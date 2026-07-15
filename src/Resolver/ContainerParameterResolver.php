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
 * class are not traversed. Every path resolves through a single {@see ResolvableDependency} plan, so the resolution
 * algorithm lives in one place.
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
                $instance = $this->tryResolveDependency($dependency);

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
                $instance = $this->tryResolveDependency($dependency);

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

    /**
     * The single resolution algorithm shared by every path: tries each alternative in priority order and returns the
     * first that resolves, or <code>null</code> if none do (so the caller can apply its fallbacks).
     */
    private function tryResolveDependency(ResolvableDependency $dependency): ?object
    {
        foreach ($dependency->alternatives as $alternative) {
            $instance = $this->resolveAlternative($alternative, $dependency->key);

            if ($instance !== null) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * Resolves a single conjunction: the first available member whose instance satisfies every member type. For a
     * lone class that is simply "resolve it if the container has it"; for an intersection it enforces the is-a-all
     * check.
     *
     * @param non-empty-list<class-string> $alternative
     */
    private function resolveAlternative(array $alternative, string|UnitEnum|null $key): ?object
    {
        foreach ($alternative as $className) {
            if (!$this->container->has($className, $key)) {
                continue;
            }

            $instance = $this->container->get($className, $key);

            if ($this->instanceSatisfiesAll($instance, $alternative)) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * @param non-empty-list<class-string> $classNames
     */
    private function instanceSatisfiesAll(object $instance, array $classNames): bool
    {
        foreach ($classNames as $className) {
            if (!$instance instanceof $className) {
                return false;
            }
        }

        return true;
    }
}
