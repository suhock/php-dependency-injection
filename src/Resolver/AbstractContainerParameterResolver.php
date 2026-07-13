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
use ReflectionType;
use Suhock\DependencyInjection\ClassNotFoundException;
use Suhock\DependencyInjection\ClassResolutionException;
use Suhock\DependencyInjection\ContainerInterface;
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

    public function resolveProperty(ReflectionProperty $rProperty, string|UnitEnum|null $key): mixed
    {
        $deferredException = null;
        $rType = $rProperty->getType();
        $dependency = $this->describeFromType($rProperty->getName(), $rType, $key);

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
     * Builds the resolution plan for an injection point from its type, or <code>null</code> if the type is absent or
     * not resolvable from the container (untyped, builtin, or an unsupported composite such as a DNF whose members are
     * not plain named types).
     *
     * @param string $name The name of the injection point, carried into the descriptor for diagnostics and overrides
     * @param ReflectionType|null $rType The declared type of the injection point
     * @param string|UnitEnum|null $key The key to resolve by, if any
     */
    protected function describeFromType(
        string $name,
        ?ReflectionType $rType,
        string|UnitEnum|null $key
    ): ?ResolvableDependency {
        return ResolvableDependencyFactory::createFromType($name, $rType, $key);
    }
}
