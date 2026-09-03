<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Compiler;

use Suhock\DependencyInjection\Lazy;
use Suhock\DependencyInjection\Resolver\ResolvableDependency;

/**
 * One dependency edge of a {@see ResolutionPlan}: an injection point the container satisfies when the service
 * resolves. Immutable and free of reflection objects.
 *
 * A soft edge self-heals at runtime: when resolution fails, the injection point falls back to its declared
 * default (when {@see $hasDefault}, evaluated freshly per resolution) or <code>null</code>, so it can never
 * be a guaranteed failure on its own. An edge whose {@see $dependency} is <code>null</code> describes an
 * injection point the container is never consulted for (untyped, builtin, or an unsupported composite type);
 * if such an edge is not soft, resolution is guaranteed to throw, which validation reports at build time.
 *
 * A {@see $lazy} edge (its parameter carries {@see Lazy}) is satisfied with a PHP native lazy object of the resolved
 * type, deferring the dependency's construction until first use.
 *
 * A {@see $self} edge names the service the factory itself produces, and is satisfied by constructing that service's
 * class rather than by consulting the container. It is never soft: construction does not fail the way a container
 * lookup can.
 *
 * @internal
 */
final class ResolutionPlanEdge
{
    /**
     * @param string $name The parameter name of the injection point
     * @param ResolvableDependency|null $dependency The container-resolvable description, or <code>null</code> if the
     *     container is never consulted for this injection point
     * @param bool $soft Whether resolution failure self-heals via the default value or <code>null</code>
     * @param bool $hasDefault Whether the injection point declares a default value
     * @param string|null $declaredType The raw declared type, populated only when {@see $dependency} is
     *     <code>null</code> and the injection point has a type, for diagnostics
     * @param bool $lazy Whether the dependency is injected lazily (the parameter carries {@see Lazy})
     * @param bool $self Whether the injection point names the service the factory produces, satisfied by construction
     *     rather than by a container lookup
     */
    public function __construct(
        public readonly string $name,
        public readonly ?ResolvableDependency $dependency,
        public readonly bool $soft,
        public readonly bool $hasDefault = false,
        public readonly ?string $declaredType = null,
        public readonly bool $lazy = false,
        public readonly bool $self = false,
    ) {}

}
