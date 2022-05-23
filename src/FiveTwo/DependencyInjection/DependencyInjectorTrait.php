<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Provides default boilerplate behavior for the {@see DependencyInjectorInterface} interface.
 *
 * @psalm-require-implements DependencyInjectorInterface
 */
trait DependencyInjectorTrait
{
    /**
     * Attempts to resolve a value for the specified parameter. If the function fails to resolve a value and a default
     * value is available, that value will be used, so implementers do not need to perform this check.
     *
     * @param ReflectionParameter $rParam The reflection parameter
     * @param mixed $paramValue Reference parameter to receive the resolved parameter
     *
     * @return bool <code>true</code> if a value was resolved, <code>false</code> otherwise
     */
    protected abstract function tryResolveParameter(ReflectionParameter $rParam, mixed &$paramValue): bool;

    /**
     * Calls the specified function, injecting any function parameter values.
     *
     * @param callable $function The function to call
     *
     * @return mixed The value returned by the function
     * @throws DependencyInjectionException If there was an error resolving values for the function parameters or
     * invoking the function
     */
    public function call(callable $function): mixed
    {
        is_callable($function, false, $functionName);

        try {
            $rFunction = new ReflectionFunction($function(...));
        } catch (ReflectionException $e) {
            // The callable type constraint should make this unreachable
            throw new DependencyInjectionException("Function $functionName() does not exist", $e);
        }

        return self::invoke(
            $rFunction->invokeArgs(...),
            $rFunction->getParameters(),
            $functionName
        );
    }

    /**
     * Creates a new instance of the specified class, injecting any constructor parameter values.
     *
     * @template T
     *
     * @param class-string<T> $className The name of the class to instantiate
     *
     * @return T A new instance of the specified class
     * @throws DependencyInjectionException If there was an error resolving values for the constructor parameters or
     * invoking the constructor
     */
    public function instantiate(string $className): object
    {
        try {
            $rClass = new ReflectionClass($className);
        } catch (ReflectionException $e) {
            throw new DependencyInjectionException("Class $className does not exist", $e);
        }

        if (!$rClass->isInstantiable()) {
            throw new DependencyInjectionException("Class $className is not instantiable");
        }

        return self::invoke(
            $rClass->newInstanceArgs(...),
            $rClass->getConstructor()?->getParameters() ?? [],
            "$className::__construct()"
        );
    }

    /**
     * @param callable(array):mixed $function
     * @param ReflectionParameter[] $rParameters
     * @param string $functionName
     *
     * @return mixed
     * @throws DependencyInjectionException If a value could not be resolved for any of the parameters
     */
    private function invoke(callable $function, array $rParameters, string $functionName): mixed
    {
        $paramValues = [];

        foreach ($rParameters as $rParam) {
            $paramValues[] = self::resolveParameter($rParam, $functionName);
        }

        return $function($paramValues);
    }

    /**
     * @param ReflectionParameter $rParam
     * @param string $functionName
     *
     * @return mixed|null
     * @throws UnresolvedParameterException If a value could not be resolved for the parameter
     */
    private function resolveParameter(ReflectionParameter $rParam, string $functionName): mixed
    {
        if (self::tryResolveParameter($rParam, $paramValue)) {
            return $paramValue;
        }

        if ($rParam->isDefaultValueAvailable()) {
            return $rParam->getDefaultValue();
        }

        throw new UnresolvedParameterException(
            $functionName,
            $rParam->getName(),
            $rParam->getType() instanceof ReflectionNamedType ? $rParam->getType()->getName() : null,
        );
    }
}
