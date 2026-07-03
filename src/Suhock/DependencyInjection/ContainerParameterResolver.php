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
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
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
     * Builds the resolution plan from the parameter's type, honoring any {@see Key} attribute. Named, union,
     * intersection, and DNF types all map to the disjunction-of-conjunctions in {@see ResolvableDependency}. Returns
     * <code>null</code> for an untyped or otherwise non-resolvable type.
     */
    protected function describeDependency(ReflectionParameter $rParam): ?ResolvableDependency
    {
        $rType = $rParam->getType();

        if ($rType === null) {
            return null;
        }

        $alternatives = $this->alternativesFromType($rType);

        return $alternatives === null ? null : new ResolvableDependency(
            $rParam->getName(),
            $alternatives,
            $this->keyFromAttributes($rParam->getAttributes(Key::class))
        );
    }

    /**
     * @return non-empty-list<non-empty-list<class-string>>|null
     */
    private function alternativesFromType(ReflectionType $rType): ?array
    {
        if ($rType instanceof ReflectionNamedType) {
            $className = $this->classNameFromNamedType($rType);

            return $className === null ? null : [[$className]];
        }

        if ($rType instanceof ReflectionIntersectionType) {
            $members = $this->intersectionMembers($rType);

            return $members === null ? null : [$members];
        }

        if ($rType instanceof ReflectionUnionType) {
            return $this->unionAlternatives($rType);
        }

        return null;
    }

    /**
     * @return non-empty-list<non-empty-list<class-string>>|null
     */
    private function unionAlternatives(ReflectionUnionType $rType): ?array
    {
        $alternatives = [];

        foreach ($rType->getTypes() as $rInnerType) {
            // A union's members are named types or, in DNF, intersections.
            if ($rInnerType instanceof ReflectionIntersectionType) {
                $members = $this->intersectionMembers($rInnerType);

                if ($members === null) {
                    return null;
                }

                $alternatives[] = $members;

                continue;
            }

            $className = $this->classNameFromNamedType($rInnerType);

            if ($className !== null) {
                // A builtin alternative is skipped, mirroring the by-type resolution of a union.
                $alternatives[] = [$className];
            }
        }

        return $alternatives === [] ? null : $alternatives;
    }

    /**
     * @return class-string|null The class name, or <code>null</code> for a builtin type
     */
    private function classNameFromNamedType(ReflectionNamedType $rType): ?string
    {
        if ($rType->isBuiltin()) {
            return null;
        }

        /** @var class-string $className a named, non-builtin type is a class name */
        $className = $rType->getName();

        return $className;
    }

    /**
     * @return non-empty-list<class-string>|null <code>null</code> if any member is not a plain named type
     */
    private function intersectionMembers(ReflectionIntersectionType $rType): ?array
    {
        $members = [];

        foreach ($rType->getTypes() as $rInnerType) {
            if (!$rInnerType instanceof ReflectionNamedType) {
                // Future-proofing. As of PHP 8.1, only named types are supported in intersection types.
                return null;
            }

            /** @var class-string $className */
            $className = $rInnerType->getName();
            $members[] = $className;
        }

        return $members === [] ? null : $members;
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
