<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Compiler;

/**
 * One out-edge of a {@see ResolutionPlan} after resolution against the frozen descriptor map: the service the runtime
 * would traverse, and the facts its consumers project. Produced by {@see PlanEdgeResolver}.
 *
 * @internal
 */
final class ResolvedEdge
{
    /**
     * @param string $targetId The descriptor id the runtime would resolve for this injection point
     * @param string $injectionPoint The injection point on the source service, e.g.
     *     <code>parameter $transport of __construct()</code>
     * @param bool $required Whether resolution fails without the target; a soft (defaulted or nullable) injection
     *     point self-heals instead
     * @param bool $lazy Whether the target is injected as a lazy object, deferring its construction past the source
     *     service's own
     */
    public function __construct(
        public readonly string $targetId,
        public readonly string $injectionPoint,
        public readonly bool $required,
        public readonly bool $lazy,
    ) {}
}
