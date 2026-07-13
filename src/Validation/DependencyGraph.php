<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Validation;

/**
 * A configuration's dependency graph, exported for external tooling: every service and every satisfied, chosen edge
 * between them, exactly as resolution would traverse them. Roots (services nothing injects), reachability, renderings
 * such as Graphviz, and dead-service linting are all derivable from this plain data.
 *
 * The export mirrors what the runtime and validation see: unsatisfiable injection points produce no edge (they are
 * validation's domain), an added-but-never-chosen union member receives no incoming edge, and dependencies hidden
 * inside custom instance providers are invisible.
 */
final class DependencyGraph
{
    /**
     * @param list<string> $serviceIds Every service in the built configuration, as <code>Class</code> or
     * <code>Class#key</code>, in configuration order — including the automatic self-bindings
     * @param list<DependencyGraphEdge> $edges Every satisfied, chosen dependency edge
     */
    public function __construct(
        public readonly array $serviceIds,
        public readonly array $edges
    ) {
    }
}
