<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Lifetime\InstanceStore;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;

/**
 * Identifies the resolution root (the root container or a scope) a service is being resolved from, so that a
 * {@see LifetimeStrategy} can decide where, if anywhere, to cache the instances it hands out, and so that instance
 * providers resolve a service's dependencies from the same root.
 *
 * @internal Only the container's own resolution machinery (lifetime strategies and instance providers, themselves
 * internal) ever receives a context.
 */
final class ResolutionContext
{
    /**
     * @param ContainerInterface $container The resolution root itself
     * @param InstanceStore $store The instance store owned by {@see $container}
     * @param ResolutionContext|null $root The context of the root container, or <code>null</code> if this context
     * belongs to the root container itself
     */
    public function __construct(
        public readonly ContainerInterface $container,
        public readonly InstanceStore $store,
        public readonly ?ResolutionContext $root = null
    ) {
    }

    /**
     * Returns the context of the root container.
     */
    public function rootContext(): self
    {
        return $this->root ?? $this;
    }
}
