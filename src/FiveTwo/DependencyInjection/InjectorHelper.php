<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
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
