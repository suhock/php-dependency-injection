<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use ReflectionAttribute;
use ReflectionNamedType;
use ReflectionParameter;
use UnitEnum;
use function count;

/**
 * Resolves function parameters using a {@see ContainerInterface}, honoring the {@see Key} attribute on a parameter. A
 * keyed parameter is resolved absolutely against that key; an unkeyed parameter is resolved by type. Only the parameter
 * itself is inspected — no scope is tracked and the declaring function and class are not traversed.
 *
 * @internal
 */
final class ContainerParameterResolver extends AbstractContainerParameterResolver implements
    TypeParameterResolverInterface
{
    protected function tryResolveParameter(ReflectionParameter $rParam, ?object &$result): bool
    {
        return $this->tryGetInstanceFromParameter(
            $rParam,
            $result,
            $this->keyFromAttributes($rParam->getAttributes(Key::class))
        );
    }

    /**
     * Eligible exactly when {@see resolveParameter()} would reduce to a single lookup of one class name (honoring any
     * {@see Key} attribute) with no fallback. The exclusions below must stay in step with the fallbacks in
     * {@see AbstractContainerParameterResolver::resolveParameter()} (default, null) and the union/intersection handling
     * above; the key, by contrast, is carried into the descriptor rather than excluded.
     */
    public function getResolvableDependency(ReflectionParameter $rParam): ?ResolvableDependency
    {
        $rType = $rParam->getType();

        if (
            !$rType instanceof ReflectionNamedType ||
            $rType->isBuiltin() ||
            $rParam->allowsNull() ||
            $rParam->isDefaultValueAvailable()
        ) {
            return null;
        }

        /** @var class-string $className a named, non-builtin type is a class name */
        $className = $rType->getName();

        return new ResolvableDependency($className, $this->keyFromAttributes($rParam->getAttributes(Key::class)));
    }

    /**
     * @param array<ReflectionAttribute<Key>> $rAttributes
     */
    private function keyFromAttributes(array $rAttributes): string|UnitEnum|null
    {
        foreach ($rAttributes as $rAttribute) {
            /** @var list<string|UnitEnum> $args */
            $args = $rAttribute->getArguments();

            if (count($args) > 0) {
                return $args[0];
            }
        }

        return null;
    }
}
