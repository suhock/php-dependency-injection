<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Injection;

use ReflectionClass;
use Suhock\DependencyInjection\Inject;
use Suhock\DependencyInjection\InjectorException;
use Suhock\DependencyInjection\Key;

use function count;

/**
 * Reflects a class's {@see Inject} injection points into an {@see InjectionPlan}: the methods to invoke and a map of
 * properties to assign, each of any visibility. Private members declared by a parent class are not visible to the scan
 * and are not injected. Used by {@see InjectAttributeMemberInjector} to compute a plan on a cache miss.
 */
final class InjectionPlanFactory
{
    /**
     * @param class-string $className
     *
     * @throws InjectorException if a method with an {@see Inject} attribute is static, or a property has a
     * {@see Key} attribute but no {@see Inject} attribute
     */
    public static function create(string $className): InjectionPlan
    {
        $class = new ReflectionClass($className);
        $methods = [];
        $properties = [];

        foreach ($class->getMethods() as $rMethod) {
            if (count($rMethod->getAttributes(Inject::class)) === 0) {
                continue;
            }

            if ($rMethod->isStatic()) {
                throw new InjectorException(
                    "Method $className::" . $rMethod->getName()
                        . '() is static and cannot be an #[Inject] method; injection happens per instance',
                );
            }

            $methods[] = $rMethod->getName();
        }

        foreach ($class->getProperties() as $rProperty) {
            // A promoted property is constructor-injected; a Key on it qualifies the constructor parameter.
            if ($rProperty->isPromoted()) {
                continue;
            }

            $rKeyAttributes = $rProperty->getAttributes(Key::class);

            if (count($rProperty->getAttributes(Inject::class)) === 0) {
                if (count($rKeyAttributes) > 0) {
                    throw new InjectorException(
                        "Property $className::\$" . $rProperty->getName()
                            . ' has a #[Key] attribute but no #[Inject]; a key alone does not mark a property'
                            . ' for injection',
                    );
                }

                continue;
            }

            $properties[$rProperty->getName()] = count($rKeyAttributes) > 0
                ? $rKeyAttributes[0]->newInstance()->getKey()
                : null;
        }

        return new InjectionPlan($methods, $properties);
    }
}
