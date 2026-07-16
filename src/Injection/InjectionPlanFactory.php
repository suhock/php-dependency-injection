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
use Suhock\DependencyInjection\Resolver\ResolvableDependencyFactory;

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
     * @throws InjectorException if a method with an {@see Inject} attribute is static, if a property has a
     *     {@see Key} attribute but no {@see Inject} attribute, or if an {@see Inject} property has a type that can
     *     never resolve to a service (untyped, scalar, or object)
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

            $key = count($rKeyAttributes) > 0 ? $rKeyAttributes[0]->newInstance()->getKey() : null;

            if (ResolvableDependencyFactory::createFromType($rProperty->getType(), $key) === null) {
                throw new InjectorException(
                    "Property $className::\$" . $rProperty->getName()
                        . ' has an #[Inject] attribute but its type cannot be resolved from the container;'
                        . ' #[Inject] requires a class or interface type',
                );
            }

            $properties[$rProperty->getName()] = $key;
        }

        return new InjectionPlan($methods, $properties);
    }
}
