<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use UnitEnum;

/**
 * The class name(s) and optional key that satisfy an injection point, produced by
 * {@see ResolvableDependencyFactory} and consumed by the resolvers and the build-time compiler. Immutable and free
 * of reflection objects, so it is cheap to cache inside a compiled resolution plan.
 *
 * The candidates are expressed as a type is in disjunctive normal form: {@see $alternatives} is a priority-ordered
 * disjunction (tried first-available), and each inner list is a conjunction a single instance must satisfy. So a named
 * type is one alternative of one class, a union is several single-class alternatives, an intersection is one
 * multi-class alternative, and a DNF type such as <code>(A&B)|C</code> mixes both (<code>[[A, B], [C]]</code>).
 */
final class ResolvableDependency
{
    /**
     * @param non-empty-list<non-empty-list<class-string>> $alternatives The candidate resolutions, in priority order;
     *     each inner list is a set of types a single instance must satisfy
     * @param string|UnitEnum|null $key The key the service was added under, if any
     */
    public function __construct(
        public readonly array $alternatives,
        public readonly string|UnitEnum|null $key = null,
    ) {}

    /**
     * Restores an instance from <code>var_export</code> output, which emits the promoted properties by name.
     *
     * @param array<string, mixed> $state
     */
    public static function __set_state(array $state): self
    {
        // @phpstan-ignore argument.type (var_export output of an instance of this class)
        return new self(...$state);
    }
}
