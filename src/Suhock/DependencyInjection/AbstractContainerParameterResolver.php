<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use ReflectionParameter;
use UnitEnum;

/**
 * Abstract base class for {@see ParameterResolverInterface} implementations that resolve dependencies from an
 * implementation of {@see ContainerInterface}. Both the fast path ({@see resolveDependency()}) and the reflection path
 * ({@see resolveParameter()}) resolve through a single {@see ResolvableDependency} plan, so the resolution algorithm
 * lives in one place.
 */
abstract class AbstractContainerParameterResolver implements ParameterResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    public function hasDependency(ResolvableDependency $dependency): bool
    {
        foreach ($dependency->alternatives as $alternative) {
            foreach ($alternative as $className) {
                if ($this->container->has($className, $dependency->key)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function resolveDependency(ResolvableDependency $dependency): object
    {
        return $this->tryResolveDependency($dependency)
            ?? throw new ClassNotFoundException($dependency->alternatives[0][0]);
    }

    /**
     * The single resolution algorithm shared by both paths: tries each alternative in priority order and returns the
     * first that resolves, or <code>null</code> if none do (so the reflection path can apply its fallbacks).
     */
    protected function tryResolveDependency(ResolvableDependency $dependency): ?object
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
     * lone class that is simply "resolve it if registered"; for an intersection it enforces the is-a-all check.
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

    /**
     * Should build the resolution plan for the parameter, or <code>null</code> if it has no container-resolvable type.
     *
     * @param ReflectionParameter $rParam The parameter to describe
     *
     * @return ResolvableDependency|null The plan to resolve, or <code>null</code> if the parameter cannot be resolved
     * from the container by type
     */
    abstract protected function describeDependency(ReflectionParameter $rParam): ?ResolvableDependency;

    public function resolveParameter(ReflectionParameter $rParam): mixed
    {
        $deferredException = null;

        try {
            $dependency = $this->describeDependency($rParam);

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
}
