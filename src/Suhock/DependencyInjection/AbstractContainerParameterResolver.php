<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022-2023 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Abstract base class for {@see ParameterResolverInterface} implementations that resolve dependencies from an
 * implementation of {@see ContainerInterface}.
 */
abstract class AbstractContainerParameterResolver implements ParameterResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    /**
     * Should attempt to resolve the parameter to a concrete value using the container.
     *
     * @param ReflectionParameter $rParam The parameter for which to attempt to resolve a value
     * @param mixed $result Reference parameter that will receive a concrete value for the parameter if one can be
     * resolved
     *
     * @return bool <code>true</code> if a value could be resolved, <code>false</code> otherwise
     */
    abstract protected function tryResolveParameter(ReflectionParameter $rParam, mixed &$result): bool;

    public function resolveParameter(ReflectionParameter $rParam): mixed
    {
        $deferredException = null;

        try {
            if ($this->tryResolveParameter($rParam, $paramValue)) {
                return $paramValue;
            }
        } catch (ClassResolutionException $e) {
            $deferredException = $e;
        }

        if ($rParam->isDefaultValueAvailable()) {
            return $rParam->getDefaultValue();
        }

        if ($rParam->allowsNull()) {
            return null;
        }

        throw new ParameterResolutionException($rParam, $deferredException);
    }

    protected function tryGetInstanceFromParameter(ReflectionParameter $rParam, mixed &$result): bool
    {
        return $rParam->getType() !== null && $this->tryGetInstanceFromType($rParam->getType(), $result);
    }

    /**
     * @param-out object|null $result
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
     * @param-out object|null $result
     */
    protected function tryGetFromNamedType(ReflectionNamedType $rType, mixed &$result): bool
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
     * @param-out object|null $result
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
     * @param-out object|null $result
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

            if (!$this->container->has($className)) {
                continue;
            }

            // only way to tell if it's a match is to get an instance and check
            $instance = $this->container->get($className);

            if ($this->isIntersectionMatch($rType, $instance)) {
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
