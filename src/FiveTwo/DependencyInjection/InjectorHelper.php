<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use ReflectionNamedType;
use ReflectionParameter;

class InjectorHelper
{
    private function __construct()
    {
    }

    /**
     * @param ReflectionParameter $rParam
     *
     * @return class-string|null
     */
    public static function getClassNameFromParameter(ReflectionParameter $rParam): string|null
    {
        /** @phpstan-ignore-next-line ReflectionType::getName() will return a class name given the condition */
        return ($rParam->getType() instanceof ReflectionNamedType && !$rParam->getType()->isBuiltin()) ?
            $rParam->getType()->getName() :
            null;
    }
}
