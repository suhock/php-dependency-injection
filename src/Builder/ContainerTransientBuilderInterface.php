<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use Suhock\DependencyInjection\InstanceProvider\ImplementationException;
use UnitEnum;

/**
 * Interface for adding transient factories to a container.
 */
interface ContainerTransientBuilderInterface
{
    /**
     * Indicates that the container should provide a transient instance of the given class by autowiring its
     * constructor. An optional mutator function can be specified to perform additional initialization on the
     * constructed object.
     *
     * @param class-string $className The fully qualified name of the class to add
     * @param callable|null $mutator [optional] This function will be called after an instance of the class has been
     * created. The class instance will be provided as the first parameter. Any additional parameters will be injected.
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addTransientClass(string $className, ?callable $mutator = null): static;

    /**
     * Indicates that the container should provide a transient instance of the given class under the given key by
     * autowiring its constructor. An optional mutator function can be specified to perform additional initialization
     * on the constructed object.
     *
     * @param class-string $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param callable|null $mutator [optional] This function will be called after an instance of the class has been
     * created. The class instance will be provided as the first parameter. Any additional parameters will be injected.
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addKeyedTransientClass(
        string $className,
        string|UnitEnum $key,
        ?callable $mutator = null
    ): static;

    /**
     * Indicates that the container should provide a transient instance of the given class by retrieving an instance of
     * the specified implementation class from the container. The container must also specify how to resolve the
     * implementation class.
     *
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param class-string<TImplementation> $implementationClassName The fully qualified name of a class that implements
     * or extends {@see $className}.
     *
     * @return $this
     * @throws ImplementationException If the implementation class is not a subclass of the class being added
     */
    public function addTransientImplementation(string $className, string $implementationClassName): static;

    /**
     * Indicates that the container should provide a transient instance of the given class under the given key by
     * retrieving an instance of the specified implementation class from the container. The container must also
     * specify how to resolve the implementation class.
     *
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param class-string<TImplementation> $implementationClassName The fully qualified name of a class that
     * implements or extends {@see $className}.
     *
     * @return $this
     * @throws ImplementationException If the implementation class is not a subclass of the class being added
     */
    public function addKeyedTransientImplementation(
        string $className,
        string|UnitEnum $key,
        string $implementationClassName
    ): static;

    /**
     * Indicates that the container should provide a transient instance of the given class by calling the specified
     * factory method.
     *
     * @param class-string $className The fully qualified name of the class to add
     * @param callable $factory A factory method that returns an instance of the class specified by {@see $className}.
     * Any method parameters will be injected.
     *
     * @return $this
     */
    public function addTransientFactory(string $className, callable $factory): static;

    /**
     * Indicates that the container should provide a transient instance of the given class under the given key by
     * calling the specified factory method.
     *
     * @param class-string $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param callable $factory A factory method that returns an instance of the class specified by {@see $className}.
     * Any method parameters will be injected.
     *
     * @return $this
     */
    public function addKeyedTransientFactory(string $className, string|UnitEnum $key, callable $factory): static;
}
