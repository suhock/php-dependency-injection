<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use Closure;
use Suhock\DependencyInjection\InstanceProvider\ImplementationException;
use Suhock\DependencyInjection\ScopeException;
use Suhock\DependencyInjection\ScopeInterface;
use UnitEnum;

/**
 * Interface for adding scoped factories to a container. A scoped service is instantiated once per {@see ScopeInterface}
 * and its dependencies are resolved from the scope; requesting one with no scope active throws a {@see ScopeException}.
 */
interface ContainerScopedBuilderInterface
{
    /**
     * Adds a scoped service. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `Closure`: a factory to call.
     * - an `object`: the instance to use.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|TClass|Closure|null $source
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (Closure parameters are injected)
    public function addScoped(string $className, string|object|null $source = null): static;

    /**
     * Adds a scoped service under $key. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `Closure`: a factory to call.
     * - an `object`: the instance to use.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param string|UnitEnum $key The key to add the service under
     * @param class-string<TClass>|TClass|Closure|null $source
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addKeyedScoped(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null,
    ): static;

    /**
     * Indicates that the container should provide a scoped instance of the given class by autowiring its constructor.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     *
     * @throws ImplementationException
     *
     * @return $this
     */
    public function addScopedClass(string $className): static;

    /**
     * Indicates that the container should provide a scoped instance of the given class under the given key by
     * autowiring its constructor.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     *
     * @throws ImplementationException
     *
     * @return $this
     */
    public function addKeyedScopedClass(string $className, string|UnitEnum $key): static;

    /**
     * Indicates that the container should provide a per-scope instance of the given class by retrieving an instance of
     * the specified implementation class from the resolving scope. The container must also specify how to resolve the
     * implementation class.
     *
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param class-string<TImplementation> $implementationClassName The fully qualified name of a class that implements
     *     or extends {@see $className}.
     *
     * @throws ImplementationException If the implementation class is not a subclass of the class being added
     *
     * @return $this
     */
    public function addScopedImplementation(string $className, string $implementationClassName): static;

    /**
     * Indicates that the container should provide a per-scope instance of the given class under the given key by
     * retrieving an instance of the specified implementation class from the resolving scope. The container must also
     * specify how to resolve the implementation class.
     *
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param class-string<TImplementation> $implementationClassName The fully qualified name of a class that
     *     implements or extends {@see $className}.
     *
     * @throws ImplementationException If the implementation class is not a subclass of the class being added
     *
     * @return $this
     */
    public function addKeyedScopedImplementation(
        string $className,
        string|UnitEnum $key,
        string $implementationClassName,
    ): static;

    /**
     * Indicates that the container should provide a per-scope instance of the given class by calling the specified
     * factory method.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param callable $factory A factory method that returns an instance of the class specified by {@see $className}.
     *     Any method parameters will be injected from the resolving scope.
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addScopedFactory(string $className, callable $factory): static;

    /**
     * Indicates that the container should provide a per-scope instance of the given class under the given key by
     * calling the specified factory method.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param callable $factory A factory method that returns an instance of the class specified by {@see $className}.
     *     Any method parameters will be injected from the resolving scope.
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addKeyedScopedFactory(string $className, string|UnitEnum $key, callable $factory): static;
}
