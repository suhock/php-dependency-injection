<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

use function array_key_exists;
use function count;
use function is_callable;

/**
 * Default implementation for {@see InjectorInterface} that resolves missing parameter values using a
 * {@see ParameterResolverInterface}.
 */
class Injector implements InjectorInterface
{
    /**
     * @param ParameterResolverInterface $resolver The resolver to use for resolving parameters
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly ParameterResolverInterface $resolver
    ) {
    }

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
                $params
            )
        );
    }

    /**
     * @template TClass of object
     * @psalm-param class-string<TClass> $className
     * @psalm-return TClass
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
                    $rClass->getConstructor()
                        ?->getParameters() ?? [],
                    $params
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
     * @phpstan-param array<mixed> $params
     *
     * @return list<mixed>
     */
    private function resolveParameterList(array $rParameters, array $params): array
    {
        /** @var list<mixed> $paramValues */
        $paramValues = [];

        foreach ($rParameters as $rParam) {
            /** @psalm-suppress MixedAssignment $paramValues is declared as list<mixed>... */
            $paramValues[] = $this->resolveParameter($rParam, $params);
        }

        return $paramValues;
    }

    /**
     * @phpstan-param array<mixed> $params
     */
    private function resolveParameter(ReflectionParameter $rParam, array $params): mixed
    {
        return match (true) {
            array_key_exists($rParam->getPosition(), $params) => $params[$rParam->getPosition()],
            array_key_exists($rParam->getName(), $params) => $params[$rParam->getName()],
            default => $this->resolver->resolveParameter($rParam)
        };
    }
}
