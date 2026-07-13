<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Resolver;

use ReflectionParameter;
use ReflectionProperty;
use UnitEnum;

/**
 * Provides methods for resolving injection points (function parameters and injected properties) to concrete values.
 */
interface ParameterResolverInterface
{
    /**
     * @throws ParameterResolutionException If a value for the parameter could not be resolved
     */
    public function resolveParameter(ReflectionParameter $rParam): mixed;

    /**
     * @param ReflectionProperty $rProperty The property to resolve a value for
     * @param string|UnitEnum|null $key The key to resolve the property's type by, if any
     *
     * @throws PropertyResolutionException If a value for the property could not be resolved
     */
    public function resolveProperty(ReflectionProperty $rProperty, string|UnitEnum|null $key): mixed;
}
