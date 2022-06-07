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
        return match (true) {
            $rType instanceof ReflectionNamedType => $this->tryGetFromNamedType($rType, $result),
            $rType instanceof ReflectionUnionType => $this->tryGetFromUnionType($rType, $result),
            $rType instanceof ReflectionIntersectionType => $this->tryGetFromIntersectionType($rType, $result),
            default => false // encountered an unknown ReflectionType
        };
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
                // Future-proofing. As of PHP 8.1, only named types are supported in intersection types.
                return false;
            }

            /** @var class-string $className */
            $className = $rInnerType->getName();

            if (!$this->getContainer()->has($className)) {
                continue;
            }

            // only way to tell if it's a match is to get an instance and check
            $instance = $this->getContainer()->get($className);

            if ($instance !== null && $this->isIntersectionMatch($rType, $instance)) {
                $result = $instance;

                return true;
            }
        }

        return false;
    }

    private function isIntersectionMatch(ReflectionIntersectionType $rType, object $instance): bool
    {
        foreach ($rType->getTypes() as $rInnerType) {
            if (!$rInnerType instanceof ReflectionNamedType) {
                // Future-proofing. As of PHP 8.1, only named types are supported in intersection types.
                return false;
            }

            /** @var class-string $testClassName */
            $testClassName = $rInnerType->getName();

            if (!$instance instanceof $testClassName) {
                return false;
            }
        }

        return true;
    }
}
