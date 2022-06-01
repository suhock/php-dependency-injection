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

/**
 * Helper methods for resolving function dependencies from a container.
 */
trait ContainerInjectorTrait
{
    abstract protected function getContainer(): ContainerInterface;

    /**
     * @param ReflectionParameter $rParam
     * @param mixed $result
     * @param-out object|null $result
     *
     * @return bool
     */
    protected function getInstanceFromParameter(ReflectionParameter $rParam, mixed &$result): bool
    {
        return $rParam->getType() !== null && $this->tryGetInstanceFromType($rParam->getType(), $result);
    }

    /**
     * @param ReflectionType $rType
     * @param mixed $result
     * @param-out object|null $result
     *
     * @return bool
     */
    private function tryGetInstanceFromType(ReflectionType $rType, mixed &$result): bool
    {
        if ($rType instanceof ReflectionNamedType) {
            return $this->tryGetFromNamedType($rType, $result);
        } elseif ($rType instanceof ReflectionUnionType) {
            return $this->tryGetFromUnionType($rType, $result);
        } elseif ($rType instanceof ReflectionIntersectionType) {
            return $this->tryGetFromIntersectionType($rType, $result);
        }

        return false;
    }

    /**
     * @param ReflectionNamedType $rType
     * @param mixed $result
     * @param-out object|null $result
     *
     * @return bool
     */
    private function tryGetFromNamedType(ReflectionNamedType $rType, mixed &$result): bool
    {
        /** @phpstan-ignore-next-line PHPStan is not able to figure out that getName() will return a class name */
        if ($rType->isBuiltin() || !$this->container->has($rType->getName())) {
            return false;
        }

        /** @phpstan-ignore-next-line PHPStan is not able to figure out that getName() will return a class name */
        $result = $this->container->get($rType->getName());

        return true;
    }

    /**
     * @param ReflectionUnionType $rType
     * @param mixed $result
     * @param-out object|null $result
     *
     * @return bool
     */
    private function tryGetFromUnionType(ReflectionUnionType $rType, mixed &$result): bool
    {
        foreach ($rType->getTypes() as $rInnerType) {
            if ($this->tryGetFromNamedType($rInnerType, $result)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param ReflectionIntersectionType $rType
     * @param mixed $result
     * @param-out ?object $result
     *
     * @return bool
     */
    private function tryGetFromIntersectionType(
        ReflectionIntersectionType $rType,
        mixed &$result
    ): bool {
        foreach ($rType->getTypes() as $rInnerType) {
            if (!$rInnerType instanceof ReflectionNamedType) {
                return false;
            }
        }

        foreach ($rType->getTypes() as $rInnerType) {
            /**
             * @psalm-suppress UndefinedMethod
             * @var class-string $className
             */
            $className = $rInnerType->getName();

            if (!$this->getContainer()->has($className)) {
                continue;
            }

            // only way to tell if it's a match is to instantiate it
            $instance = $this->getContainer()->get($className);

            foreach ($rType->getTypes() as $rTestType) {
                /**
                 * @psalm-suppress UndefinedMethod
                 * @var class-string $testClassName
                 */
                $testClassName = $rTestType->getName();

                if (!$instance instanceof $testClassName) {
                    continue 2;
                }
            }

            $result = $instance;

            return true;
        }

        return false;
    }
}
