<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Injection;

use ReflectionMethod;
use ReflectionProperty;
use Suhock\DependencyInjection\Cache\CacheInterface;
use Suhock\DependencyInjection\Cache\MetadataCache;
use Suhock\DependencyInjection\Inject;
use Suhock\DependencyInjection\Instantiation\PostInstantiationHookInterface;
use Suhock\DependencyInjection\Resolver\ArgumentResolver;
use Suhock\DependencyInjection\Resolver\ParameterResolverInterface;

/**
 * Post-instantiation hook that fills the {@see Inject} injection points on a new instance — invoking its Inject methods
 * and assigning its Inject properties — resolving values through a {@see ParameterResolverInterface}. The injection
 * points are computed via reflection once per class and cached as an {@see InjectionPlan}, so subsequent injections
 * skip the scan.
 */
final class InjectAttributeMemberInjector implements PostInstantiationHookInterface
{
    /** Cache id prefix for the {@see InjectionPlan} of a class. */
    private const INJECTION_PLAN_CACHE_PREFIX = 'sdi:injectionPlan:';

    private readonly MetadataCache $cache;

    private readonly ArgumentResolver $argumentResolver;

    /**
     * @param ParameterResolverInterface $resolver The resolver to use for resolving injection point values
     * @param CacheInterface|null $sharedCache [optional] Optional shared (L2) metadata cache for the reflected
     * injection plans
     */
    public function __construct(
        private readonly ParameterResolverInterface $resolver,
        ?CacheInterface $sharedCache = null
    ) {
        $this->cache = new MetadataCache($sharedCache);
        $this->argumentResolver = new ArgumentResolver($resolver);
    }

    public function postInstantiate(object $instance): void
    {
        $plan = $this->getInjectionPlan($instance::class);

        foreach ($plan->properties as $propertyName => $key) {
            $rProperty = new ReflectionProperty($instance, $propertyName);
            $rProperty->setValue($instance, $this->resolver->resolveProperty($rProperty, $key));
        }

        foreach ($plan->methods as $methodName) {
            $rMethod = new ReflectionMethod($instance, $methodName);
            $rMethod->invokeArgs($instance, $this->argumentResolver->resolve($rMethod->getParameters(), []));
        }
    }

    /**
     * Returns the {@see Inject} injection points on the given class — the methods to invoke and the properties to
     * assign, each of any visibility — computing them via reflection on the first request and caching the result so
     * subsequent injections skip the scan. Private members declared by a parent class are not visible to the scan and
     * are not injected.
     *
     * @param class-string $className
     */
    private function getInjectionPlan(string $className): InjectionPlan
    {
        return $this->cache->get(
            self::INJECTION_PLAN_CACHE_PREFIX . $className,
            static fn () => InjectionPlanFactory::create($className)
        );
    }
}
