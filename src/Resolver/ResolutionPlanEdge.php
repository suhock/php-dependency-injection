<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

/**
 * One dependency edge of a {@see ResolutionPlan}: an injection point the container will attempt to satisfy when the
 * service resolves. Immutable and free of reflection objects.
 *
 * A soft edge self-heals at runtime — the injection point falls back to its default value or <code>null</code> when
 * resolution fails — so it can never be a guaranteed failure on its own. An edge whose {@see $dependency} is
 * <code>null</code> describes an injection point the container is never consulted for (untyped, builtin, or an
 * unsupported composite type); if such an edge is not soft, resolution is guaranteed to throw.
 *
 * @internal
 */
final class ResolutionPlanEdge
{
    /**
     * @param string $memberDescription Human-readable location of the injection point, e.g.
     * <code>parameter $transport of __construct()</code> or <code>property $logger</code>
     * @param ResolvableDependency|null $dependency The container-resolvable description, or <code>null</code> if the
     * container is never consulted for this injection point
     * @param bool $soft Whether resolution failure self-heals via a default value or <code>null</code>
     * @param string|null $declaredType The raw declared type, populated only when {@see $dependency} is
     * <code>null</code> and the injection point has a type, for diagnostics
     * @param bool $isImplementation Whether this edge is an implementation reference, whose target must itself be a
     * resolvable service
     */
    public function __construct(
        public readonly string $memberDescription,
        public readonly ?ResolvableDependency $dependency,
        public readonly bool $soft,
        public readonly ?string $declaredType = null,
        public readonly bool $isImplementation = false
    ) {
    }
}
