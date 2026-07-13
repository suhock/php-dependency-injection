<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\InstanceProvider;

use Closure;

/**
 * Describes a {@see ClassInstanceProvider}: the instance is autowired by instantiating {@see $className} and, if
 * {@see $mutator} is present, invoking it with the new instance as its first argument. The mutator's remaining
 * parameters, from index 1 onward, are themselves dependencies.
 */
final class AutowireClassSource implements DependencySource
{
    /**
     * @param class-string $className The class to instantiate
     * @param Closure|null $mutator The mutator invoked with the new instance, or <code>null</code> if none is set
     */
    public function __construct(
        public readonly string $className,
        public readonly ?Closure $mutator
    ) {
    }
}
