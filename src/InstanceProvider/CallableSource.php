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
 * Describes a {@see ClosureInstanceProvider}: the instance is produced by invoking {@see $callable}, whose first
 * {@see $skipLeadingParams} parameters are not dependencies, and whose return value is expected to satisfy
 * {@see $declaredType}.
 */
final class CallableSource implements DependencySource
{
    /**
     * @param Closure $callable The factory function that produces the instance
     * @param int<0, max> $skipLeadingParams The number of leading parameters of {@see $callable} that are not
     * dependencies
     * @param class-string $declaredType The class the return value of {@see $callable} is expected to satisfy
     */
    public function __construct(
        public readonly Closure $callable,
        public readonly int $skipLeadingParams,
        public readonly string $declaredType
    ) {
    }
}
