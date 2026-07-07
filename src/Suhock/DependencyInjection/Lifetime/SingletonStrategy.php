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
 * Manages the lifetime of a singleton object: one instance per root store, created on first request and reused for the
 * store's lifetime.
 *
 * @template TClass of object
 * @extends LifetimeStrategy<TClass>
 */
final class SingletonStrategy extends LifetimeStrategy
{
    /**
     * @inheritDoc
     */
    public function get(ResolutionContext $context, callable $factory): object
    {
        return $context->rootStore->getOrCreate($this, $factory);
    }
}
