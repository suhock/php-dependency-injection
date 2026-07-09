<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Instantiation;

use ReflectionClass;
use ReflectionException;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\Cache\MetadataCache;
use Suhock\DependencyInjection\Resolver\ResolvableDependency;
use Suhock\DependencyInjection\Resolver\TypeParameterResolverInterface;

use function array_key_exists;

/**
 * Instantiates a class from a cached list of directly resolvable dependency descriptors, skipping per-instantiation
 * reflection entirely. Applies only when every constructor parameter is a non-variadic dependency the resolver reports
 * as directly resolvable (see {@see TypeParameterResolverInterface::getResolvableDependency()}); otherwise it declines
 * by returning <code>null</code> so a later strategy (typically {@see ReflectionInstantiationStrategy}) can handle it.
 * Caller-supplied parameters override the resolved value for the matching slot — by position, then by name.
 */
final class FastPathInstantiationStrategy implements InstantiationStrategyInterface
{
    /** Cache id prefix for the fast-path constructor dependency list of a class. */
    private const DEPS_CACHE_PREFIX = 'sdi:fastPathDeps:';

    private readonly MetadataCache $cache;

    /**
     * @param TypeParameterResolverInterface $resolver Resolver used to classify and resolve dependencies by type
     * @param CacheInterface|null $sharedCache [optional] Shared (L2) cache for the reflected dependency lists
     */
    public function __construct(
        private readonly TypeParameterResolverInterface $resolver,
        ?CacheInterface $sharedCache = null
    ) {
        $this->cache = new MetadataCache($sharedCache);
    }

    public function tryInstantiate(string $className, array $params): ?object
    {
        $deps = $this->getDependencies($className);

        if ($deps === false || !$this->areAllResolvable($deps, $params)) {
            return null;
        }

        $args = [];

        foreach ($deps as $position => $dependency) {
            $args[] = match (true) {
                array_key_exists($position, $params) => $params[$position],
                array_key_exists($dependency->name, $params) => $params[$dependency->name],
                default => $this->resolver->resolveDependency($dependency)
            };
        }

        return new $className(...$args);
    }

    /**
     * Returns the cached dependency descriptor list for the class, or <code>false</code> if it is not fast-path
     * eligible.
     *
     * @param class-string $className
     *
     * @return list<ResolvableDependency>|false
     */
    private function getDependencies(string $className): array|false
    {
        return $this->cache->get(
            self::DEPS_CACHE_PREFIX . $className,
            fn () => $this->analyzeConstructor($className) ?? false
        );
    }

    /**
     * Reflects the constructor once to decide fast-path eligibility. A class is eligible only if every constructor
     * parameter is a non-variadic dependency the resolver reports as directly resolvable. Ineligible or non-reflectable
     * classes return <code>null</code> so the caller falls back to the reflection path (which also produces the correct
     * diagnostics).
     *
     * @param class-string $className
     *
     * @return list<ResolvableDependency>|null
     */
    private function analyzeConstructor(string $className): ?array
    {
        try {
            $rClass = new ReflectionClass($className);
            /** @phpstan-ignore catch.neverThrown (a class-string may reference an unloadable class at runtime) */
        } catch (ReflectionException) {
            return null;
        }

        if (!$rClass->isInstantiable()) {
            return null;
        }

        $deps = [];

        foreach ($rClass->getConstructor()?->getParameters() ?? [] as $rParam) {
            // Variadic parameters are a structural limit of the fast path (it cannot spread args); everything else
            // about resolvability -- type shape, nullability, defaults, keying -- is the resolver's decision.
            if ($rParam->isVariadic()) {
                return null;
            }

            $dependency = $this->resolver->getResolvableDependency($rParam);

            if ($dependency === null) {
                return null;
            }

            $deps[] = $dependency;
        }

        return $deps;
    }

    /**
     * A class is fast-path resolvable when every dependency is either supplied by the caller (matched by position or
     * name, so no resolution is needed) or reported available by the resolver.
     *
     * @param list<ResolvableDependency> $deps
     * @param array<mixed> $params
     */
    private function areAllResolvable(array $deps, array $params): bool
    {
        foreach ($deps as $position => $dependency) {
            if (array_key_exists($position, $params) || array_key_exists($dependency->name, $params)) {
                continue;
            }

            if (!$this->resolver->hasDependency($dependency)) {
                return false;
            }
        }

        return true;
    }
}
