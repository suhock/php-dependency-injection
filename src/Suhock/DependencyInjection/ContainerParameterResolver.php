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
    /**
     * Fast-path eligible when {@see resolveParameter()} would reduce to resolving the same candidate(s) the reflection
     * path would, with no fallback. Nullable/defaulted parameters are excluded so the fallbacks in
     * {@see AbstractContainerParameterResolver::resolveParameter()} still apply; otherwise the plan is identical.
     */
    public function getResolvableDependency(ReflectionParameter $rParam): ?ResolvableDependency
    {
        if ($rParam->allowsNull() || $rParam->isDefaultValueAvailable()) {
            return null;
        }

        return $this->describeDependency($rParam);
    }

    /**
     * Builds the resolution plan from the parameter's type, honoring any {@see Key} attribute.
     */
    protected function describeDependency(ReflectionParameter $rParam): ?ResolvableDependency
    {
        return $this->describeFromType(
            $rParam->getName(),
            $rParam->getType(),
            $this->keyFromAttributes($rParam->getAttributes(Key::class))
        );
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
