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
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;
use Throwable;

/**
 * Exception that indicates the injector could not resolve a value for a function parameter.
 */
class ParameterResolutionException extends InjectorException
{
    /**
     * @param ReflectionParameter $reflectionParameter The unresolved parameter
     * @param Throwable|null $previous [optional] The previous throwable used for exception chaining. If the throwable
     * is an instance of {@see DependencyInjectionException} then its content will be consolidated into the new
     * instance.
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly ReflectionParameter $reflectionParameter,
        ?Throwable $previous = null
    ) {
        parent::__construct(self::buildMessage($reflectionParameter), $previous);
    }

    public function getReflectionParameter(): ReflectionParameter
    {
        return $this->reflectionParameter;
    }

    private static function buildMessage(ReflectionParameter $rParam): string
    {
        $functionName = self::buildFunctionName($rParam);
        $paramName = self::buildParameterName($rParam);

        return "Could not provide a value for parameter $paramName in $functionName";
    }

    private static function buildFunctionName(ReflectionParameter $rParam): string
    {
        $rFunction = $rParam->getDeclaringFunction();
        $functionName = $rFunction->getName();

        if ($rFunction instanceof ReflectionMethod) {
            $functionName = $rFunction->getDeclaringClass() . "::$functionName";
        }

        if (!$rFunction->isClosure()) {
            $functionName = "function $functionName()";
        } elseif ($rFunction->getClosureScopeClass()) {
            $functionName .= ' scoped in ' .
                $rFunction->getClosureScopeClass()->getName();
        }

        return $functionName;
    }

    private static function buildParameterName(ReflectionParameter $rParam): string
    {
        $paramName = '$' . $rParam->getName();
        $paramType = self::buildParameterTypeName($rParam->getType());

        if ($paramType !== null) {
            $paramName = "$paramType $paramName";
        }

        return $paramName;
    }

    /**
     * @psalm-pure
     */
    private static function buildParameterTypeName(?ReflectionType $rType): ?string
    {
        return match (true) {
            $rType instanceof ReflectionNamedType => $rType->getName(),
            $rType instanceof ReflectionUnionType => self::buildCombinedParameterTypeName($rType, '|'),
            $rType instanceof ReflectionIntersectionType => self::buildCombinedParameterTypeName($rType, '&'),
            default => null // covers null $rType as well as any new types introduced after PHP 8.1
        };
    }

    /**
     * @psalm-pure
     */
    private static function buildCombinedParameterTypeName(
        ReflectionUnionType|ReflectionIntersectionType $rType,
        string $delimiter
    ): string {
        $parts = [];

        foreach ($rType->getTypes() as $rNestedType) {
            $parts[] = self::buildParameterTypeName($rNestedType);
        }

        return implode($delimiter, $parts);
    }
}
