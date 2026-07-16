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
use ReflectionParameter;
use Suhock\DependencyInjection\ClassResolutionException;
use Suhock\DependencyInjection\ConcreteClassNameProviderInterface;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\InjectorException;
use Suhock\DependencyInjection\Key;
use Suhock\DependencyInjection\Lazy;
use UnitEnum;

use function class_exists;
use function count;

/**
 * Resolves a function parameter from a {@see ContainerInterface}, honoring the {@see Key} attribute on the parameter.
 * A keyed parameter is resolved absolutely against that key; an unkeyed one is resolved by type. Only the parameter
 * itself is inspected. The parameter's type maps to a single {@see ResolvableDependency}, resolved through
 * {@see DependencyResolver}.
 *
 * A {@see Lazy} parameter is satisfied with a native lazy proxy that defers resolution until first use. A proxy needs
 * a concrete class up front: the injector takes it from the parameter's own type when that is a concrete class, or,
 * when the type is an interface, from a container that implements {@see ConcreteClassNameProviderInterface}. When
 * neither yields a lazy-able concrete class, the {@see Lazy} parameter is rejected.
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
     * Resolves the parameter's dependency to an instance lazily when the parameter carries {@see Lazy}, or
     * <code>null</code> when the dependency is absent, leaving the soft fallback to {@see resolveParameter()}.
     *
     * @throws InjectorException If a {@see Lazy} parameter cannot be built as a lazy object
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
     * @throws InjectorException If the dependency cannot be built as a lazy object
     */
    private function resolveLazy(ReflectionParameter $rParam, ResolvableDependency $dependency): ?object
    {
        $className = self::singleType($dependency);

        if ($className === null) {
            throw new InjectorException(
                'Cannot lazily inject parameter $' . $rParam->getName()
                    . ': #[Lazy] requires a single class or interface type.',
            );
        }

        if (!$this->container->has($className, $dependency->key)) {
            return null;
        }

        $proxyClass = $this->lazyProxyClass($className, $dependency->key);

        if ($proxyClass === null) {
            throw new InjectorException(
                'Cannot lazily inject parameter $' . $rParam->getName() . " ($className): the injector cannot"
                    . ' determine a concrete class with properties to defer. Resolve it through a container that'
                    . ' provides concrete class names, or declare a concrete type.',
            );
        }

        return new ReflectionClass($proxyClass)->newLazyProxy(
            fn(object $proxy): object => DependencyResolver::resolve($dependency, $this->container)
                ?? throw new ParameterResolutionException($rParam),
        );
    }

    /**
     * The single class-or-interface type the dependency resolves by, or <code>null</code> for a union or
     * intersection (which cannot map to one lazy object).
     *
     * @return class-string|null
     */
    private static function singleType(ResolvableDependency $dependency): ?string
    {
        if (count($dependency->alternatives) !== 1) {
            return null;
        }

        $alternative = $dependency->alternatives[0];

        return count($alternative) === 1 ? $alternative[0] : null;
    }

    /**
     * The concrete, lazy-able class a proxy of the dependency can reflect: the type itself when it is such a class,
     * otherwise the concrete class a {@see ConcreteClassNameProviderInterface} container reports for it.
     *
     * @param class-string $className
     *
     * @return class-string|null
     */
    private function lazyProxyClass(string $className, string|UnitEnum|null $key): ?string
    {
        if (self::classCanBeLazy($className)) {
            return $className;
        }

        if ($this->container instanceof ConcreteClassNameProviderInterface) {
            $concrete = $this->container->getConcreteClassName($className, $key);

            if ($concrete !== null && self::classCanBeLazy($concrete)) {
                return $concrete;
            }
        }

        return null;
    }

    /**
     * Whether a class can be represented as a PHP native lazy object: a concrete, instantiable class that declares at
     * least one non-static property (PHP has no state to defer for a property-less class).
     *
     * @param class-string $className
     */
    private static function classCanBeLazy(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $rClass = new ReflectionClass($className);

        if (!$rClass->isInstantiable()) {
            return false;
        }

        foreach ($rClass->getProperties() as $property) {
            if (!$property->isStatic()) {
                return true;
            }
        }

        return false;
    }
}
