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

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

class InjectorHelper
{
    private function __construct()
    {
    }

    /**
     * @param ContainerInterface $container
     * @param ReflectionParameter $rParam
     * @param mixed $result
     * @param-out object|null $result
     *
     * @return bool
     */
    public static function getInstanceFromParameter(
        ContainerInterface $container,
        ReflectionParameter $rParam,
        mixed &$result
    ): bool {
        foreach (self::getClassNamesFromParameter($container, $rParam) as $className) {
            if ($container->has($className)) {
                $result = $container->get($className);

                return true;
            }
        }

        return false;
    }

    /**
     * @param ContainerInterface $container
     * @param ReflectionParameter $rParam
     *
     * @return list<class-string>
     */
    private static function getClassNamesFromParameter(
        ContainerInterface $container,
        ReflectionParameter $rParam
    ): array {
        return $rParam->getType() !== null ? self::getClassNamesFromType($container, $rParam->getType()) : [];
    }

    /**
     * @param ContainerInterface $container
     * @param ReflectionType $rType
     *
     * @return list<class-string>
     */
    private static function getClassNamesFromType(ContainerInterface $container, ReflectionType $rType): array
    {
        $classNames = [];

        if ($rType instanceof ReflectionUnionType) {
            self::addClassNamesFromUnionType($classNames, $rType);
        } elseif ($rType instanceof ReflectionIntersectionType) {
            self::addClassNameFromIntersectionType($classNames, $container, $rType);
        } elseif ($rType instanceof ReflectionNamedType) {
            self::addClassNameFromNamedType($classNames, $rType);
        }

        return $classNames;
    }

    /**
     * @param list<class-string> $classNames
     * @param ReflectionUnionType $rType
     *
     * @return void
     */
    private static function addClassNamesFromUnionType(array &$classNames, ReflectionUnionType $rType): void
    {
        foreach ($rType->getTypes() as $rInnerType) {
            self::addClassNameFromNamedType($classNames, $rInnerType);
        }
    }

    /**
     * @param list<class-string> $classNames
     * @param ContainerInterface $container
     * @param ReflectionIntersectionType $rType
     *
     * @return void
     */
    private static function addClassNameFromIntersectionType(
        array &$classNames,
        ContainerInterface $container,
        ReflectionIntersectionType $rType
    ): void {
        foreach ($rType->getTypes() as $rInnerType) {
            if (!$rInnerType instanceof ReflectionNamedType) {
                return;
            }

            /** @var class-string $className */
            $className = $rInnerType->getName();

            if (!$container->has($className)) {
                continue;
            }

            $instance = $container->get($className);

            foreach ($rType->getTypes() as $rTestType) {
                if (!$rTestType instanceof ReflectionNamedType) {
                    return;
                }

                $testClassName = $rTestType->getName();

                if (!$instance instanceof $testClassName) {
                    continue 2;
                }
            }

            $classNames[] = $className;
            return;
        }
    }

    /**
     * @param list<class-string> $classNames
     * @param ReflectionNamedType $rType
     *
     * @return void
     */
    private static function addClassNameFromNamedType(array &$classNames, ReflectionNamedType $rType): void
    {
        if (!$rType->isBuiltin()) {
            $classNames[] = $rType->getName();
        }
    }
}
