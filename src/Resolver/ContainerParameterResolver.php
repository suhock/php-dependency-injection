<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use Suhock\DependencyInjection\ClassResolutionException;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\InjectorException;
use Suhock\DependencyInjection\Key;
use Suhock\DependencyInjection\Lazy;

use function class_exists;
use function count;

/**
 * Resolves a function parameter from a {@see ContainerInterface}, honoring the {@see Key} attribute on the parameter.
 * A keyed parameter is resolved absolutely against that key; an unkeyed one is resolved by type. Only the parameter
 * itself is inspected. The parameter's type maps to a single {@see ResolvableDependency}, resolved through
 * {@see DependencyResolver}.
 *
 * A {@see Lazy} parameter is satisfied with a native lazy proxy that defers resolution until first use. Without a
 * compiled plan the injector can only proxy a parameter whose declared type is itself a concrete class; a
 * {@see Lazy} parameter typed as an interface or abstract class cannot be built here and is rejected.
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
            $instance = $this->tryResolve($rParam);

            if ($instance !== null) {
                return $instance;
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

    /**
     * Resolves the parameter's dependency to an instance lazily when the parameter carries {@see Lazy} or
     * <code>null</code> when the dependency is absent, leaving the soft fallback to {@see resolveParameter()}.
     *
     * @throws InjectorException If a {@see Lazy} parameter's declared type is not a concrete class the injector can
     *     construct lazily
     * @throws ClassResolutionException If a candidate fails while resolving its own graph
     */
    private function tryResolve(ReflectionParameter $rParam): ?object
    {
        $dependency = ResolvableDependencyFactory::createFromParameter($rParam);

        if ($dependency === null) {
            return null;
        }

        if (count($rParam->getAttributes(Lazy::class)) === 0) {
            return DependencyResolver::resolve($dependency, $this->container);
        }

        return $this->resolveLazy($rParam, $dependency);
    }

    /**
     * Builds a lazy proxy for a {@see Lazy} parameter, or <code>null</code> when the dependency is not registered
     * (its presence is tested without constructing anything, so a soft parameter still falls back to its default or
     * <code>null</code>). The proxy resolves the real instance from the container on first use.
     *
     * @throws InjectorException If the declared type is not a single concrete class a lazy proxy can be built for
     */
    private function resolveLazy(ReflectionParameter $rParam, ResolvableDependency $dependency): ?object
    {
        $className = self::lazyProxyClass($rParam->getType());

        if ($className === null) {
            throw new InjectorException(
                'Cannot lazily inject parameter $' . $rParam->getName() . ': its declared type is not a single'
                    . ' concrete class the injector can construct lazily. Resolve it through the container or declare'
                    . ' a concrete type.',
            );
        }

        if (!$this->container->has($className, $dependency->key)) {
            return null;
        }

        return new ReflectionClass($className)->newLazyProxy(
            fn(object $proxy): object => DependencyResolver::resolve($dependency, $this->container)
                ?? throw new ParameterResolutionException($rParam),
        );
    }

    /**
     * The parameter's declared type when it is a single, non-builtin, concrete, instantiable class a lazy proxy can
     * reflect; <code>null</code> for interfaces, abstract classes, unions, intersections, builtins, and untyped
     * parameters.
     *
     * @return class-string|null
     */
    private static function lazyProxyClass(?ReflectionType $rType): ?string
    {
        if (!$rType instanceof ReflectionNamedType || $rType->isBuiltin()) {
            return null;
        }

        $className = $rType->getName();

        return class_exists($className) && new ReflectionClass($className)->isInstantiable() ? $className : null;
    }
}
