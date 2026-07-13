<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use ReflectionAttribute;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Suhock\DependencyInjection\Key;
use UnitEnum;

use function count;

/**
 * Container-independent description of an injection point's resolvable dependency: maps a parameter's or property's
 * declared type (and any {@see Key} attribute) to a {@see ResolvableDependency} disjunction of alternatives, without
 * consulting a container. Shared by {@see AbstractContainerParameterResolver} and {@see ContainerParameterResolver} so
 * the mapping from reflection to a resolution plan lives in one place.
 */
final class DependencyDescriber
{
    /**
     * Builds the resolution plan for a parameter from its type, honoring any {@see Key} attribute.
     */
    public function describeParameter(ReflectionParameter $rParam): ?ResolvableDependency
    {
        return $this->describeType(
            $rParam->getName(),
            $rParam->getType(),
            $this->keyFromAttributes($rParam->getAttributes(Key::class))
        );
    }

    /**
     * Builds the resolution plan for an injection point from its type, or <code>null</code> if the type is absent or
     * not resolvable from the container (untyped, builtin, or an unsupported composite such as a DNF whose members are
     * not plain named types).
     *
     * @param string $name The name of the injection point, carried into the descriptor for diagnostics and overrides
     * @param ReflectionType|null $rType The declared type of the injection point
     * @param string|UnitEnum|null $key The key to resolve by, if any
     */
    public function describeType(
        string $name,
        ?ReflectionType $rType,
        string|UnitEnum|null $key
    ): ?ResolvableDependency {
        if ($rType === null) {
            return null;
        }

        $alternatives = $this->alternativesFromType($rType);

        return $alternatives === null ? null : new ResolvableDependency($name, $alternatives, $key);
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

    /**
     * Named, union, intersection, and DNF types all map to the disjunction-of-conjunctions in
     * {@see ResolvableDependency}.
     *
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
}
