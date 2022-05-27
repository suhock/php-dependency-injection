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
 * Provides default boilerplate behavior for the {@see InjectorInterface} interface.
 *
 * @psalm-require-implements InjectorInterface
 */
trait InjectorTrait
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
    abstract protected function tryResolveParameter(ReflectionParameter $rParam, mixed &$paramValue): bool;

    /**
     * Calls the specified function, injecting any function parameter values.
     *
     * @param callable $function The function to call
     * @param array $params A list of parameter values to provide to the function. String keys will be matched by name;
     * integer keys will be matched by position.
     *
     * @return mixed The value returned by the function
     * @throws DependencyInjectionException If there was an error resolving values for the function parameters or
     * invoking the function
     */
    public function call(callable $function, array $params = []): mixed
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
            $params,
            $functionName
        );
    }

    /**
     * Creates a new instance of the specified class, injecting any constructor parameter values.
     *
     * @template T
     *
     * @param class-string<T> $className The name of the class to instantiate
     * @param array $params A list of parameter values to provide to the constructor. String keys will be matched by
     * name; integer keys will be matched by position.
     *
     * @return T A new instance of the specified class
     * @throws DependencyInjectionException If there was an error resolving values for the constructor parameters or
     * invoking the constructor
     */
    public function instantiate(string $className, array $params = []): object
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
            $params,
            "$className::__construct()"
        );
    }

    /**
     * @param callable(array):mixed $function
     * @param ReflectionParameter[] $rParameters
     * @param array $params
     * @param string $functionName
     *
     * @return mixed
     * @throws DependencyInjectionException If a value could not be resolved for any of the parameters
     */
    private function invoke(callable $function, array $rParameters, array $params, string $functionName): mixed
    {
        $paramValues = [];

        foreach ($rParameters as $rParam) {
            $paramValues[] = self::resolveParameter($rParam, $params, $functionName);
        }

        return $function($paramValues);
    }

    /**
     * @param ReflectionParameter $rParam
     * @param array $params
     * @param string $functionName
     *
     * @return mixed|null
     */
    private function resolveParameter(ReflectionParameter $rParam, array $params, string $functionName): mixed
    {
        if (array_key_exists($rParam->getPosition(), $params)) {
            return $params[$rParam->getPosition()];
        }

        if (array_key_exists($rParam->getName(), $params)) {
            return $params[$rParam->getName()];
        }

        $exception = null;

        try {
            if (self::tryResolveParameter($rParam, $paramValue)) {
                return $paramValue;
            }
        } catch (DependencyInjectionException $exception) {
        }

        if ($rParam->isDefaultValueAvailable()) {
            return $rParam->getDefaultValue();
        }

        throw new UnresolvedParameterException(
            $functionName,
            $rParam->getName(),
            $rParam->getType() instanceof ReflectionNamedType ? $rParam->getType()->getName() : null,
            $exception
        );
    }
}
