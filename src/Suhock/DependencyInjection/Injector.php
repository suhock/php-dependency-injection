<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Suhock\DependencyInjection\Cache\CacheInterface;
use function array_key_exists;
use function count;
use function is_callable;

/**
 * Default implementation for {@see InjectorInterface} that resolves missing parameter values using a
 * {@see ParameterResolverInterface}.
 */
class Injector implements InjectorInterface
{
    /** Cache id prefix for the list of {@see Autowire} method names on a class. */
    private const AUTOWIRE_METHODS_CACHE_PREFIX = 'sdi:autowireMethods:';

    /** Cache id prefix for the flat fast-path constructor dependency list of a class. */
    private const FAST_PATH_DEPS_CACHE_PREFIX = 'sdi:fastPathDeps:';

    /**
     * In-process (L1) metadata cache, living for this injector instance. Each entry is either a class's flat
     * constructor dependency list (or the <code>false</code> fast-path-ineligible sentinel) or its list of
     * {@see Autowire} method names — never reflection objects, so lookups are plain array reads with no
     * unserialization.
     *
     * @var array<string, list<string>|false>
     */
    private array $inProcessCache = [];

    /**
     * @param ParameterResolverInterface $resolver The resolver to use for resolving parameters
     * @param CacheInterface|null $sharedCache [optional] Optional shared (L2) metadata cache. The in-process (L1) cache
     * is always active for the life of this injector; supply an {@see Cache\ApcuCache} here to additionally share
     * reflected metadata across requests.
     * @param ContainerInterface|null $container [optional] The container used to resolve dependencies directly by class
     * name on the fast path. When omitted, every instantiation uses the reflection path.
     */
    public function __construct(
        private readonly ParameterResolverInterface $resolver,
        private readonly ?CacheInterface $sharedCache = null,
        private readonly ?ContainerInterface $container = null
    ) {
    }

    public function call(callable $function, array $params = []): mixed
    {
        // $function is always callable; is_callable is invoked only to capture $functionName for the error message.
        /** @phpstan-ignore-next-line function.alreadyNarrowedType */
        is_callable($function, false, $functionName);

        try {
            $rFunction = new ReflectionFunction($function(...));
        } catch (ReflectionException $e) {
            // The callable parameter type constraint should make this unreachable
            throw new InjectorException("Function $functionName() does not exist", $e);
        }

        return $rFunction->invokeArgs(
            $this->resolveParameterList($rFunction->getParameters(), $params)
        );
    }

    /**
     * @inheritDoc
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param array<mixed> $params
     *
     * @return TClass
     */
    public function instantiate(string $className, array $params = []): object
    {
        if ($this->tryInstantiateViaFastPath($className, $params, $result)) {
            /** @var TClass $result */
            return $result;
        }

        return $this->instantiateViaReflection($className, $params);
    }

    /**
     * Fast path: for a constructor whose dependencies are all plain, required, type-hinted services, resolution
     * needs nothing more than a flat list of class names resolved directly from the container -- no reflection.
     * Only taken when the caller supplied no explicit parameters and a container is available.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param array<mixed> $params
     */
    private function tryInstantiateViaFastPath(string $className, array $params, mixed &$instance): bool
    {
        if ($params === [] && $this->container !== null) {
            $deps = $this->getFastPathDependencies($className);

            if ($deps !== false && $this->areAllResolvable($deps, $this->container)) {
                $args = [];

                foreach ($deps as $dependencyClassName) {
                    $args[] = $this->container->get($dependencyClassName);
                }

                /** @var TClass $instance */
                $instance = new $className(...$args);
                $this->injectAutowireFunctions($instance);

                return true;
            }
        }

        return false;
    }

    /**
     * Returns the flat list of constructor dependency class names for the given class if it is eligible for the fast
     * path, or <code>false</code> if it is not. The result is a flat, serializable scalar value cached in both tiers.
     *
     * @param class-string $className
     *
     * @return list<class-string>|false
     */
    private function getFastPathDependencies(string $className): array|false
    {
        return $this->getOrAddToCache(
            self::FAST_PATH_DEPS_CACHE_PREFIX . $className,
            fn () => $this->analyzeConstructor($className) ?? false
        );
    }

    /**
     * Reflects the constructor once to decide fast-path eligibility. A class is eligible only if every constructor
     * parameter is a required service: a single named, non-builtin type, with no default, not nullable, not variadic,
     * and carrying no {@see Key} attribute. Ineligible or non-reflectable classes return <code>null</code> so the
     * caller falls back to the full reflection path (which also produces the correct diagnostics).
     *
     * @param class-string $className
     *
     * @return list<class-string>|null
     */
    private function analyzeConstructor(string $className): ?array
    {
        try {
            $rClass = new ReflectionClass($className);
            /** @phpstan-ignore-next-line PHPStan infers $className is always a valid class-string; a class that cannot
             * be reflected is deferred to the reflection path, which reports it. */
        } catch (ReflectionException) {
            return null;
        }

        if (!$rClass->isInstantiable()) {
            return null;
        }

        $deps = [];

        foreach ($rClass->getConstructor()?->getParameters() ?? [] as $rParam) {
            $rType = $rParam->getType();

            if (
                !$rType instanceof ReflectionNamedType ||
                $rType->isBuiltin() ||
                $rParam->isVariadic() ||
                $rParam->allowsNull() ||
                $rParam->isDefaultValueAvailable() ||
                count($rParam->getAttributes(Key::class)) > 0
            ) {
                return null;
            }

            /** @var class-string $dependencyClassName a named, non-builtin type is a class name */
            $dependencyClassName = $rType->getName();
            $deps[] = $dependencyClassName;
        }

        return $deps;
    }

    /**
     * @param list<class-string> $deps
     */
    private function areAllResolvable(array $deps, ContainerInterface $container): bool
    {
        foreach ($deps as $dependencyClassName) {
            if (!$container->has($dependencyClassName)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param array<mixed> $params
     *
     * @return TClass
     */
    private function instantiateViaReflection(string $className, array $params): object
    {
        try {
            $rClass = new ReflectionClass($className);
            /** @phpstan-ignore-next-line PHPStan assumes an exception can never be thrown because it infers that
             * $className will always be valid from the PHPDoc. */
        } catch (ReflectionException $e) {
            throw new InjectorException("Class $className does not exist", $e);
        }

        if (!$rClass->isInstantiable()) {
            throw new InjectorException("Class $className is not instantiable");
        }

        try {
            /** @var TClass $instance */
            $instance = $rClass->newInstanceArgs(
                $this->resolveParameterList($rClass->getConstructor()?->getParameters() ?? [], $params)
            );
        } catch (ReflectionException $e) {
            // The check for !isInstantiable() should make this unreachable
            throw new InjectorException("Could not instantiate $className", $e);
        }

        $this->injectAutowireFunctions($instance);

        return $instance;
    }

    private function injectAutowireFunctions(object $instance): void
    {
        foreach ($this->getAutowireMethods($instance::class) as $methodName) {
            $method = new ReflectionMethod($instance, $methodName);
            $closure = $method->getClosure($instance);
            $this->call($closure);
        }
    }

    /**
     * Returns the names of the public methods on the given class annotated with {@see Autowire}, computing them via
     * reflection on the first request and caching the (flat, scalar) result so subsequent instantiations skip the scan.
     *
     * @param class-string $className
     *
     * @return list<string>
     */
    private function getAutowireMethods(string $className): array
    {
        return $this->getOrAddToCache(
            self::AUTOWIRE_METHODS_CACHE_PREFIX . $className,
            /** @return list<string> */
            static function () use ($className) {
                $names = [];

                $class = new ReflectionClass($className);

                foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
                    if (count($rMethod->getAttributes(Autowire::class)) > 0) {
                        $names[] = $rMethod->getName();
                    }
                }

                return $names;
            }
        );
    }

    /**
     * Two-tier get-or-compute: request-local L1, then the optional shared L2, then compute and populate both. Only
     * flat, scalar values are ever stored, so an L2 hit is a cheap fetch rather than an object-graph unserialization.
     *
     * @template T of list<string>|false
     *
     * @param callable():T $factory
     *
     * @return T
     */
    private function getOrAddToCache(string $id, callable $factory): array|false
    {
        if (array_key_exists($id, $this->inProcessCache)) {
            /** @var T $cached */
            $cached = $this->inProcessCache[$id];

            return $cached;
        }

        if ($this->sharedCache === null || !$this->sharedCache->tryGet($id, $value)) {
            $value = $factory();
            $this->sharedCache?->set($id, $value);
        }

        /** @var T $value */
        $this->inProcessCache[$id] = $value;

        return $value;
    }

    /**
     * @param array<ReflectionParameter> $rParameters
     * @phpstan-param array<mixed> $params
     *
     * @return list<mixed>
     */
    private function resolveParameterList(array $rParameters, array $params): array
    {
        /** @var list<mixed> $paramValues */
        $paramValues = [];

        foreach ($rParameters as $rParam) {
            $paramValues[] = $this->resolveParameter($rParam, $params);
        }

        return $paramValues;
    }

    /**
     * @phpstan-param array<mixed> $params
     */
    private function resolveParameter(ReflectionParameter $rParam, array $params): mixed
    {
        return match (true) {
            array_key_exists($rParam->getPosition(), $params) => $params[$rParam->getPosition()],
            array_key_exists($rParam->getName(), $params) => $params[$rParam->getName()],
            default => $this->resolver->resolveParameter($rParam)
        };
    }
}
