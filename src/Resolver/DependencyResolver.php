<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use Closure;
use Suhock\DependencyInjection\ClassResolutionException;
use Suhock\DependencyInjection\ContainerInterface;
use UnitEnum;

/**
 * Resolves a {@see ResolvableDependency} against a container: alternatives are tried in priority order and, within an
 * alternative, the first present candidate whose instance satisfies the whole conjunction wins. Returns
 * <code>null</code> when nothing resolves, leaving any soft fallback to the caller. Shared by the container's plan
 * execution and {@see ContainerParameterResolver} so both resolve dependencies by the same algorithm.
 *
 * @internal
 */
final class DependencyResolver
{
    /**
     * @param (Closure(class-string, string|UnitEnum|null): object)|null $get Produces the instance for a present
     *     candidate; defaults to {@see ContainerInterface::get()}. The container supplies a lazy-producing closure to
     *     satisfy a {@see \Suhock\DependencyInjection\Lazy} edge without eagerly constructing the dependency. The
     *     candidate's presence is always tested with {@see ContainerInterface::has()}, so a missing candidate never
     *     invokes it.
     *
     * @throws ClassResolutionException If a candidate fails while resolving its own graph
     */
    public static function resolve(
        ResolvableDependency $dependency,
        ContainerInterface $container,
        ?Closure $get = null,
    ): ?object {
        foreach ($dependency->alternatives as $alternative) {
            $instance = self::resolveAlternative($alternative, $dependency->key, $container, $get);

            if ($instance !== null) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * @param non-empty-list<class-string> $alternative
     * @param (Closure(class-string, string|UnitEnum|null): object)|null $get
     */
    private static function resolveAlternative(
        array $alternative,
        string|UnitEnum|null $key,
        ContainerInterface $container,
        ?Closure $get,
    ): ?object {
        foreach ($alternative as $className) {
            if (!$container->has($className, $key)) {
                continue;
            }

            $instance = $get === null ? $container->get($className, $key) : $get($className, $key);

            if (self::satisfiesAll($instance, $alternative)) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * @param non-empty-list<class-string> $classNames
     */
    private static function satisfiesAll(object $instance, array $classNames): bool
    {
        foreach ($classNames as $className) {
            if (!$instance instanceof $className) {
                return false;
            }
        }

        return true;
    }
}
