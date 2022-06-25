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

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Provides default boilerplate behavior for the {@see InjectorInterface} interface.
 *
 * @psalm-require-implements InjectorInterface
 */
trait InjectorTrait
{
    /**
     * Implementors should attempt to resolve a value for the specified parameter. If the function fails to resolve a
     * value, the injector will use the parameter's default value if available, or inject null if it is a nullable type.
     * Implementors should not attempt any special handling for these cases.
     *
     * @param ReflectionParameter $rParam The reflection parameter to resolve
     * @param mixed $paramValue Reference parameter to receive the resolved parameter
     *
     * @return bool <code>true</code> if a value was resolved, <code>false</code> otherwise
     */
    abstract protected function tryResolveParameter(ReflectionParameter $rParam, mixed &$paramValue): bool;

    /**
     * Calls the specified function, injecting any parameter values. Each parameter value is determined as follows:
     *  1. From the {@see $params} array
     *  2. From the injector's dependency resolution logic (e.g. from a container)
     *  3. The parameter's default value, if available
     *  4. <code>null</code> if the parameter is nullable
     *
     * @param callable $function The function to call
     * @param array<mixed> $params A list of parameter values to provide to the function. String keys will be matched by
     * name. Integer keys will be matched by position.
     *
     * @return mixed The value returned by the function
     * @throws InjectorException If there was an error while resolving a value for any of the function parameters or
     * while invoking the function
     */
    public function call(callable $function, array $params = []): mixed
    {
        is_callable($function, false, $functionName);

        try {
            $rFunction = new ReflectionFunction($function(...));
        } catch (ReflectionException $e) {
            // The callable parameter type constraint should make this unreachable
            throw new InjectorException("Function $functionName() does not exist", $e);
        }

        return self::invoke(
            $rFunction->invokeArgs(...),
            $rFunction->getParameters(),
            $params,
            $functionName
        );
    }

    /**
     * Creates a new instance of the specified class, injecting any parameter values. Each parameter value is determined
     * as follows:
     *  1. From the {@see $params} array
     *  2. From the injector's dependency resolution logic (e.g. from a container)
     *  3. The parameter's default value, if available
     *  4. <code>null</code> if the parameter is nullable
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to instantiate
     * @param array<mixed> $params A list of parameter values to provide to the constructor. String keys will be matched
     * by name; integer keys will be matched by position.
     *
     * @return TClass A new instance of the specified class
     * @throws InjectorException If there was an error while resolving a value for any of the constructor parameters or
     * while creating the object instance
     */
    public function instantiate(string $className, array $params = []): object
    {
        try {
            $rClass = new ReflectionClass($className);
            /** @phpstan-ignore-next-line PHPStan assumes an exception can never be thrown because the static analysis
             * infers that $className will always be a valid. Don't want to assume this.
             */
        } catch (ReflectionException $e) {
            throw new InjectorException("Class $className does not exist", $e);
        }

        if (!$rClass->isInstantiable()) {
            throw new InjectorException("Class $className is not instantiable");
        }

        /** @psalm-var Closure():TClass $factory Psalm needs help resolving the return type for newInstanceArgs() */
        $factory = $rClass->newInstanceArgs(...);

        $instance = self::invoke(
            $factory,
            $rClass->getConstructor()?->getParameters() ?? [],
            $params,
            "$className::__construct"
        );

        $this->injectAutowireFunctions($instance);

        return $instance;
    }

    private function injectAutowireFunctions(object $instance): void
    {
        $rClass = new ReflectionClass($instance);

        foreach ($rClass->getMethods(ReflectionMethod::IS_PUBLIC) as $rMethod) {
            if (count($rMethod->getAttributes(Autowire::class)) > 0) {
                /** @phpstan-ignore-next-line PHPStan complains about possible null return */
                $this->call($rMethod->getClosure($instance));
            }
        }
    }

    /**
     * @template TResult
     *
     * @param callable(list<mixed>):TResult $function
     * @param array<ReflectionParameter> $rParameters
     * @param array<mixed> $params
     * @param string $functionName
     *
     * @return TResult
     * @throws DependencyInjectionException If a value could not be resolved for any of the parameters
     */
    private function invoke(callable $function, array $rParameters, array $params, string $functionName): mixed
    {
        $paramValues = [];

        foreach ($rParameters as $rParam) {
            /** @psalm-suppress MixedAssignment Type of assignment not needed for analysis */
            $paramValues[] = self::resolveParameter($rParam, $params, $functionName);
        }

        return $function($paramValues);
    }

    /**
     * @param ReflectionParameter $rParam
     * @param array<mixed> $params
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

        $deferredException = null;

        try {
            if ($this->tryResolveParameter($rParam, $paramValue)) {
                return $paramValue;
            }
        } catch (CircularDependencyException $e) {
            throw new CircularParameterException(
                $e->getClassName(),
                $functionName,
                $rParam->getName(),
                $e->getPrevious()
            );
        } catch (UnresolvedParameterException $e) {
            $deferredException = $e;
        }

        if ($rParam->isDefaultValueAvailable()) {
            return $rParam->getDefaultValue();
        }

        if ($rParam->getType()?->allowsNull()) {
            return null;
        }

        throw new UnresolvedParameterException(
            $functionName,
            $rParam->getName(),
            self::getReflectionTypeName($rParam->getType()),
            $deferredException
        );
    }

    /**
     * @psalm-pure
     */
    private static function getReflectionTypeName(?ReflectionType $rType): ?string
    {
        if ($rType === null) {
            return null;
        }

        if ($rType instanceof ReflectionNamedType) {
            return $rType->getName();
        }

        if ($rType instanceof ReflectionUnionType) {
            $delimiter = '|';
        } elseif ($rType instanceof ReflectionIntersectionType) {
            $delimiter = '&';
        } else {
            // Future-proofing. All types covered as of PHP 8.1.
            return null;
        }

        $parts = [];

        foreach ($rType->getTypes() as $rNestedType) {
            $parts[] = self::getReflectionTypeName($rNestedType);
        }

        return implode($delimiter, $parts);
    }
}
