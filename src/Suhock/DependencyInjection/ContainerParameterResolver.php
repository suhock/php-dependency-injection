<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use ReflectionParameter;

/**
 * Resolves function parameters using a {@see ContainerInterface}.
 */
class ContainerParameterResolver extends AbstractContainerParameterResolver
{
    protected function tryResolveParameter(ReflectionParameter $rParam, mixed &$result): bool
    {
        return $this->tryGetInstanceFromParameter($rParam, $result);
    }
}
