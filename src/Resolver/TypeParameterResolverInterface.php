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

/**
 * A {@see ParameterResolverInterface} that can also resolve services directly by class name. Implementing this lets the
 * {@see Injector} take its fast path without holding a reference to any concrete value source: the injector resolves
 * whole constructor dependency lists through this capability instead of reaching around the resolver to a container.
 */
interface TypeParameterResolverInterface extends ParameterResolverInterface
{
    /**
     * If the parameter is satisfied purely by a single direct lookup -- that is, {@see resolveParameter()} would return
     * exactly the service for one class name (optionally keyed), with no default, null, or union/intersection fallback --
     * returns a descriptor the caller may cache and resolve directly. Returns <code>null</code> otherwise, meaning the
     * caller must fall back to {@see resolveParameter()}. The descriptor is opaque to the caller: it is handed back
     * verbatim to {@see hasDependency()} and {@see resolveDependency()}.
     */
    public function getResolvableDependency(ReflectionParameter $rParam): ?ResolvableDependency;

    /**
     * Indicates whether a value is available for the given dependency descriptor.
     */
    public function hasDependency(ResolvableDependency $dependency): bool;

    /**
     * Resolves a value directly from the given dependency descriptor.
     *
     * @throws ClassResolutionException If a value for the dependency could not be resolved
     */
    public function resolveDependency(ResolvableDependency $dependency): object;
}
