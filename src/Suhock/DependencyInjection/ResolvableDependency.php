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
 * A directly resolvable constructor dependency — the parameter's name plus the class name and optional key that satisfy
 * it — produced by {@see TypeParameterResolverInterface::getResolvableDependency()} for a constructor parameter the
 * {@see Injector} may satisfy on its fast path. The name lets the injector match caller-supplied override arguments
 * without reflecting. Immutable and free of reflection objects, so a list of these is cheap to cache and pass back to
 * the resolver opaquely.
 */
final class ResolvableDependency
{
    /**
     * @param string $name The constructor parameter name, used to match named override arguments
     * @param class-string $className The class name of the service that satisfies the parameter
     * @param string|UnitEnum|null $key The key the service is registered under, if any
     */
    public function __construct(
        public readonly string $name,
        public readonly string $className,
        public readonly string|UnitEnum|null $key = null
    ) {
    }
}
