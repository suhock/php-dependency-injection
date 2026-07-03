<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use UnitEnum;

/**
 * A directly resolvable constructor dependency — the parameter's name plus the class name(s) and optional key that
 * satisfy it — produced by {@see TypeParameterResolverInterface::getResolvableDependency()} for a constructor parameter
 * the {@see Injector} may satisfy on its fast path. The name lets the injector match caller-supplied override arguments
 * without reflecting. Immutable and free of reflection objects, so a list of these is cheap to cache and pass back to
 * the resolver opaquely.
 *
 * The candidates are expressed as a type is in disjunctive normal form: {@see $alternatives} is a priority-ordered
 * disjunction (tried first-available), and each inner list is a conjunction a single instance must satisfy. So a named
 * type is one alternative of one class, a union is several single-class alternatives, an intersection is one
 * multi-class alternative, and a DNF type such as <code>(A&B)|C</code> mixes both (<code>[[A, B], [C]]</code>).
 */
final class ResolvableDependency
{
    /**
     * @param string $name The constructor parameter name, used to match named override arguments
     * @param non-empty-list<non-empty-list<class-string>> $alternatives The candidate resolutions, in priority order;
     * each inner list is a set of types a single instance must satisfy
     * @param string|UnitEnum|null $key The key the service is registered under, if any
     */
    public function __construct(
        public readonly string $name,
        public readonly array $alternatives,
        public readonly string|UnitEnum|null $key = null
    ) {
    }
}
