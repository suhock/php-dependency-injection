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
 * One edge of an exported {@see DependencyGraph}: the source service injects the target service through the named
 * injection point. Immutable plain data, intended for external tooling.
 */
final class DependencyGraphEdge
{
    /**
     * @param string $sourceId The service doing the injecting, as <code>Class</code> or <code>Class#key</code>
     * @param string $targetId The service being injected, as <code>Class</code> or <code>Class#key</code>
     * @param bool $required Whether resolution fails without the target; a non-required edge self-heals to the
     *     injection point's default value or <code>null</code>
     * @param string $injectionPoint The injection point on the source, e.g.
     *     <code>parameter $transport of __construct()</code>
     */
    public function __construct(
        public readonly string $sourceId,
        public readonly string $targetId,
        public readonly bool $required,
        public readonly string $injectionPoint,
    ) {}
}
