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
use Suhock\DependencyInjection\InstanceProvider\InstanceTypeException;
use UnitEnum;

/**
 * Interface for adding singleton factories to a container.
 */
interface ContainerSingletonBuilderInterface
{
    /**
     * Adds a singleton service. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * - a `class-string`: the implementation class to resolve in place of $className.
     * - a `Closure`: a factory to call.
     * - an `object`: the instance to use.
     *
     * To set disposal of a supplied instance, use {@see addSingletonInstance()} with `$shouldDispose`.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|TClass|Closure|null $source
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addSingleton(string $className, string|object|null $source = null): static;

    /**
     * Adds a singleton service under $key. The provider is chosen from the type of $source:
     * - `null`: autowire $className's constructor.
     * * - a `class-string`: the implementation class to resolve in place of $className.
     * * - a `Closure`: a factory to call.
     * * - an `object`: the instance to use.
     *
     * To set disposal of a supplied instance, use {@see addKeyedSingletonInstance()} with `$shouldDispose`.
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
    public function addKeyedSingleton(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null,
    ): static;

    /**
     * Indicates that the container should provide a singleton instance of the given class by autowiring its
     * constructor.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     *
     * @throws ImplementationException
     *
     * @return $this
     */
    public function addSingletonClass(string $className): static;

    /**
     * Indicates that the container should provide a singleton instance of the given class under the given key by
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
    public function addKeyedSingletonClass(string $className, string|UnitEnum $key): static;

    /**
     * Indicates that the container should provide a singleton instance of the given class by retrieving an instance of
     * the specified implementation class from the container. The container must also specify how to resolve the
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
    public function addSingletonImplementation(string $className, string $implementationClassName): static;

    /**
     * Indicates that the container should provide a singleton instance of the given class under the given key by
     * retrieving an instance of the specified implementation class from the container. The container must also
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
    public function addKeyedSingletonImplementation(
        string $className,
        string|UnitEnum $key,
        string $implementationClassName,
    ): static;

    /**
     * Indicates that the container should provide a singleton instance of the given class by calling the specified
     * factory method.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param callable $factory A factory method that returns an instance of the class specified by {@see $className}.
     *     Any method parameters will be injected.
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addSingletonFactory(string $className, callable $factory): static;

    /**
     * Indicates that the container should provide a singleton instance of the given class under the given key by
     * calling the specified factory method.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param callable $factory A factory method that returns an instance of the class specified by {@see $className}.
     *     Any method parameters will be injected.
     *
     * @return $this
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    public function addKeyedSingletonFactory(string $className, string|UnitEnum $key, callable $factory): static;

    /**
     * Indicates that the container should provide the given class with the specified instance of that class.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param TClass $instance An instance of the class
     * @param bool $shouldDispose Whether the container should dispose the instance, if it implements
     *     {@see \Suhock\Disposable\DisposableInterface}, when the container is disposed. Pass false to retain
     *     disposal responsibility yourself, e.g. when the instance is shared with code outside the container.
     *
     * @throws InstanceTypeException
     *
     * @return $this
     */
    public function addSingletonInstance(string $className, object $instance, bool $shouldDispose = true): static;

    /**
     * Indicates that the container should provide the given class under the given key with the specified instance of
     * that class.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param TClass $instance An instance of the class
     * @param bool $shouldDispose Whether the container should dispose the instance, if it implements
     *     {@see \Suhock\Disposable\DisposableInterface}, when the container is disposed. Pass false to retain
     *     disposal responsibility yourself, e.g. when the instance is shared with code outside the container.
     *
     * @throws InstanceTypeException
     *
     * @return $this
     */
    public function addKeyedSingletonInstance(
        string $className,
        string|UnitEnum $key,
        object $instance,
        bool $shouldDispose = true,
    ): static;
}
