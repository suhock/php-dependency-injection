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
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\Key;

/**
 * Resolves function parameters using a {@see ContainerInterface}, honoring the {@see Key} attribute on a parameter. A
 * keyed parameter is resolved absolutely against that key; an unkeyed parameter is resolved by type. Only the parameter
 * itself is inspected — no scope is tracked and the declaring function and class are not traversed.
 *
 * @internal
 */
final class ContainerParameterResolver extends AbstractContainerParameterResolver
{
    /**
     * Builds the resolution plan from the parameter's type, honoring any {@see Key} attribute.
     */
    protected function describeDependency(ReflectionParameter $rParam): ?ResolvableDependency
    {
        return ResolvableDependencyFactory::createFromParameter($rParam);
    }
}
