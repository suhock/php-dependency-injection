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
 * The cached set of {@see Inject} injection points on a class: the names of the methods to invoke and a map of property
 * names to the optional key each should be resolved by. Immutable and free of reflection objects, so it is cheap to
 * cache and reuse across instantiations.
 *
 * @internal
 */
final class InjectionPlan
{
    /**
     * @param list<string> $methods The names of the methods to invoke
     * @param array<string, string|UnitEnum|null> $properties A map of property name to the key to resolve it by
     */
    public function __construct(
        public readonly array $methods,
        public readonly array $properties
    ) {
    }
}
