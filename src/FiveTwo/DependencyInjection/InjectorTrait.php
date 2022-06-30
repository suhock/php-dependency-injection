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

use function array_key_exists;
use function count;
use function is_callable;

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

        return $rFunction->invokeArgs(
            $this->resolveParameterList(
                $rFunction->getParameters(),
                $params,
                $functionName
            )
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
            /** @phpstan-ignore-next-line PHPStan assumes an exception can never be thrown because it infers that
             * $className will always be a valid from the PHPDoc. */
        } catch (ReflectionException $e) {
            throw new InjectorException("Class $className does not exist", $e);
        }

        if (!$rClass->isInstantiable()) {
            throw new InjectorException("Class $className is not instantiable");
        }

        try {
            /** @var TClass $instance */
            $instance = $rClass->newInstanceArgs(
                $this->resolveParameterList(
                    $rClass->getConstructor()?->getParameters() ?? [],
                    $params,
                    "$className::__construct"
                )
            );
        } catch (ReflectionException $e) {
            // The check for !isInstantiable() should make this unreachable
            throw new InjectorException("Could not instantiate $className", $e);
        }

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
     * @param array<ReflectionParameter> $rParameters
     * @param array<mixed> $params
     * @param string $functionName
     * @return list<mixed>
     */
    public function resolveParameterList(array $rParameters, array $params, string $functionName): array
    {
        /** @var list<mixed> $paramValues */
        $paramValues = [];

        foreach ($rParameters as $rParam) {
            /** @psalm-suppress MixedAssignment This assignment should be mixed */
            $paramValues[] = $this->resolveParameter($rParam, $params, $functionName);
        }

        return $paramValues;
    }

    /**
     * @param ReflectionParameter $rParam
     * @param array<mixed> $params
     * @param string $functionName
     *
     * @return mixed|null
     * @throws UnresolvedParameterException|CircularParameterException
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
            /** @psalm-var CircularDependencyException<object> $e */
            throw CircularParameterException::fromCircularDependencyException($e, $functionName, $rParam->getName());
        } catch (DependencyInjectionException $e) {
            $deferredException = $e;
        }

        if ($rParam->isDefaultValueAvailable()) {
            return $rParam->getDefaultValue();
        }

        if ($rParam->allowsNull()) {
            return null;
        }

        throw new UnresolvedParameterException(
            $functionName,
            $rParam->getName(),
            self::getParameterTypeName($rParam->getType()),
            $deferredException
        );
    }

    /**
     * @psalm-pure
     */
    private static function getParameterTypeName(?ReflectionType $rType): ?string
    {
        return match (true) {
            $rType instanceof ReflectionNamedType => $rType->getName(),
            $rType instanceof ReflectionUnionType => self::getCombinedParameterTypeName($rType, '|'),
            $rType instanceof ReflectionIntersectionType => self::getCombinedParameterTypeName($rType, '&'),
            default => null // covers null $rType as well as any new types introduced after PHP 8.1
        };
    }

    /**
     * @psalm-pure
     */
    public static function getCombinedParameterTypeName(
        ReflectionUnionType|ReflectionIntersectionType $rType,
        string $delimiter
    ): string {
        $parts = [];

        foreach ($rType->getTypes() as $rNestedType) {
            $parts[] = self::getParameterTypeName($rNestedType);
        }

        return implode($delimiter, $parts);
    }
}
