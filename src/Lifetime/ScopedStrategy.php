<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

use Override;
use Suhock\DependencyInjection\ResolutionContext;
use Suhock\DependencyInjection\ScopeException;

/**
 * Manages the lifetime of a scoped object: one instance per scope, created on first request within a scope and reused
 * for the scope's lifetime. Requesting the instance with no scope active (directly from the root container, or from a
 * singleton's dependency graph, which always resolves in the root context) throws a {@see ScopeException}.
 *
 * @template TClass of object
 *
 * @extends LifetimeStrategy<TClass>
 *
 * @internal
 */
final class ScopedStrategy extends LifetimeStrategy
{
    /**
     * @inheritDoc
     *
     * @throws ScopeException If no scope is active in the given context
     */
    #[Override]
    public function get(ResolutionContext $context, callable $factory): object
    {
        if ($context->root === null) {
            throw new ScopeException("Cannot resolve scoped service $this->className outside of a scope");
        }

        return $context->store->getOrCreate($this, static fn() => $factory($context));
    }
}
