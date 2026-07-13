<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Lifetime;

use Suhock\DependencyInjection\ResolutionContext;

/**
 * Manages the lifetime of a singleton object: one instance per root container, created on first request — from the root
 * or from any of its scopes — and reused for the root's lifetime. The instance is created in the root container's
 * context, so its dependencies never come from a shorter-lived scope.
 *
 * @template TClass of object
 * @extends LifetimeStrategy<TClass>
 *
 * @internal
 */
final class SingletonStrategy extends LifetimeStrategy
{
    /**
     * @inheritDoc
     */
    public function get(ResolutionContext $context, callable $factory): object
    {
        $home = $context->rootContext();

        return $home->store->getOrCreate($this, static fn () => $factory($home));
    }
}
