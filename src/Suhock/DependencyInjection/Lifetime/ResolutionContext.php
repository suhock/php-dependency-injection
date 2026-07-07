<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

/**
 * Identifies where a resolution is taking place, so a {@see LifetimeStrategy} can decide where, if anywhere, to cache
 * the instances it hands out.
 */
final class ResolutionContext
{
    /**
     * @param InstanceStore $rootStore Store owned by the root container; lives as long as the container itself
     * @param InstanceStore|null $scopeStore Store owned by the currently resolving scope, or <code>null</code> when
     * resolving directly from the root container with no scope active
     */
    public function __construct(
        public readonly InstanceStore $rootStore,
        public readonly ?InstanceStore $scopeStore = null
    ) {
    }
}
