<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\PHPStan;

use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Rules\Methods\AlwaysUsedMethodExtension;
use Suhock\DependencyInjection\Inject;

/**
 * Teaches PHPStan that a method with the {@see Inject} attribute is invoked by the injector via reflection, so a
 * private inject method is not dead code.
 */
final class InjectMethodAlwaysUsedExtension implements AlwaysUsedMethodExtension
{
    public function isAlwaysUsed(ExtendedMethodReflection $methodReflection): bool
    {
        foreach ($methodReflection->getAttributes() as $attribute) {
            if ($attribute->getName() === Inject::class) {
                return true;
            }
        }

        return false;
    }
}
