<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provision;

use Closure;
use DomainException;
use ReflectionException;
use ReflectionFunction;
use ReflectionNamedType;
use Suhock\DependencyInjection\DependencyInjectionException;
use Suhock\DependencyInjection\InjectorInterface;

/**
 * Factory that provides instances of a class by directly instantiating the class.
 *
 * @template TClass of object
 * @template-implements InstanceProviderInterface<TClass>
 */
class ClassInstanceProvider implements InstanceProviderInterface
{
    private readonly ?Closure $mutator;

    /**
     * @param class-string<TClass> $className The name of the class this factory will instantiate
     * @param InjectorInterface $injector The injector that will be used for instantiation
     * @param callable|null $mutator [optional] Mutator function that allows additional changes to the instantiated
     * instance. The first parameter will be the new object instance. Any other parameters will be injected.
     */
    public function __construct(
        private readonly string $className,
        private readonly InjectorInterface $injector,
        ?callable $mutator = null
    ) {
        $this->mutator = $mutator !== null ? $mutator(...) : null;
    }

    /**
     * @return TClass A new instance of {@see $className}
     * @throws DependencyInjectionException If there was an error resolving values for the constructor parameters or
     * invoking the constructor
     */
    public function get(): object
    {
        $instance = $this->injector->instantiate($this->className);

        if ($this->mutator !== null) {
            $this->injector->call($this->mutator, [$instance]);
        }

        return $instance;
    }

    /**
     * @param Closure|string $function The function to test
     * @psalm-param Closure|callable-string $function
     * @param class-string $className The name of the class that the function must be able to mutate
     *
     * @return bool true if the function can mutate the specified class; otherwise, false
     */
    public static function isMutator(callable $function, string $className): bool
    {
        try {
            $closureReflection = new ReflectionFunction($function);
        } catch (ReflectionException $e) {
            throw new DomainException('Function cannot be reflected', previous: $e);
        }

        if ($closureReflection->getNumberOfParameters() < 1) {
            return false;
        }

        $firstParamType = $closureReflection->getParameters()[0]->getType();

        if (!$firstParamType instanceof ReflectionNamedType || $firstParamType->isBuiltin()) {
            return false;
        }

        /** @var class-string $firstParamTypeName */
        $firstParamTypeName = $firstParamType->getName();

        return $firstParamTypeName === $className || is_subclass_of($className, $firstParamTypeName);
    }
}
