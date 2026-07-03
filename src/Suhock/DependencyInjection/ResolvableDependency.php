<?php
/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use UnitEnum;

/**
 * A directly resolvable service reference — a class name and optional key — produced by
 * {@see TypeParameterResolverInterface::getResolvableDependency()} for a constructor parameter the {@see Injector} may
 * satisfy on its fast path. Immutable and free of reflection objects, so a list of these is cheap to cache and pass
 * back to the resolver opaquely.
 */
final class ResolvableDependency
{
    /**
     * @param class-string $className
     */
    public function __construct(
        public readonly string $className,
        public readonly string|UnitEnum|null $key = null
    ) {
    }
}
