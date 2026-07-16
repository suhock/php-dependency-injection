<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

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
     * @throws ClassResolutionException If a candidate fails while resolving its own graph
     */
    public static function resolve(ResolvableDependency $dependency, ContainerInterface $container): ?object
    {
        foreach ($dependency->alternatives as $alternative) {
            $instance = self::resolveAlternative($alternative, $dependency->key, $container);

            if ($instance !== null) {
                return $instance;
            }
        }

        return null;
    }

    /**
     * @param non-empty-list<class-string> $alternative
     */
    private static function resolveAlternative(
        array $alternative,
        string|UnitEnum|null $key,
        ContainerInterface $container,
    ): ?object {
        foreach ($alternative as $className) {
            if (!$container->has($className, $key)) {
                continue;
            }

            $instance = $container->get($className, $key);

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
