<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Provision\ImplementationException;
use Suhock\DependencyInjection\Provision\InstanceProviderInterface;
use Suhock\DependencyInjection\Provision\InstanceTypeException;

/**
 * Interface for adding singleton factories to a container.
 */
interface ContainerSingletonBuilderInterface
{
    /**
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param class-string<TImplementation>|object|null $source
     *
     * - If null, indicates that the container should provide an instance of the given class by autowiring its
     *   constructor.
     * - If a string, indicates that the container should provide an instance of the given class by retrieving an
     *   instance of the specified implementation class from the container. The container must also specify how to
     *   resolve the implementation class.
     * - If a closure that accepts an object of the specified class as the first parameter, indicates that the container
     *   should provide an instance of the given class by autowiring its constructor and then passing the constructed
     *   object to the mutator function.
     * - If any other closure, indicates that the container should provide an instance of the given class by calling the
     *   closure.
     * - If an object, indicates that the container should provide the given object as an instance of the given class.
     *
     * @return $this
     */
    public function addSingleton(string $className, string|object|null $source = null): static;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param InstanceProviderInterface<TClass> $instanceProvider
     *
     * @return $this
     * @psalm-external-mutation-free
     */
    public function addSingletonInstanceProvider(
        string $className,
        InstanceProviderInterface $instanceProvider
    ): static;

    /**
     * Indicates that the container should provide a singleton instance of the given class by autowiring its
     * constructor. An optional mutator function can be specified to perform additional initialization on the
     * constructed object.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param callable|null $mutator [optional] This function will be called after an instance of the class has been
     * created. The class instance will be provided as the first parameter. Any additional parameters will be injected.
     *
     * @return $this
     * @throws ImplementationException
     * @psalm-external-mutation-free
     */
    public function addSingletonClass(string $className, ?callable $mutator = null): static;

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
     * or extends {@see $className}.
     *
     * @return $this
     * @throws ImplementationException If the implementation class is not a subclass of the class being added
     * @psalm-external-mutation-free
     */
    public function addSingletonImplementation(string $className, string $implementationClassName): static;

    /**
     * Indicates that the container should provide a singleton instance of the given class by calling the specified
     * factory method.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param callable $factory A factory method that returns an instance of the class specified by {@see $className}.
     * Any method parameters will be injected.
     *
     * @return $this
     * @psalm-external-mutation-free
     */
    public function addSingletonFactory(string $className, callable $factory): static;

    /**
     * Indicates that the container should provide the given class with the specified instance of that class.
     *
     * @template TClass of object
     * @template TInstance of TClass
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param TInstance $instance An instance of the class
     *
     * @return $this
     * @throws InstanceTypeException
     * @psalm-external-mutation-free
     */
    public function addSingletonInstance(string $className, object $instance): static;

    /**
     * @param ContainerInterface $container
     *
     * @return $this
     * @psalm-external-mutation-free
     */
    public function addSingletonContainer(ContainerInterface $container): static;

    /**
     * Indicates the container should provide singleton instances of classes within the given namespace using the
     * specified factory method.
     *
     * @param string $namespace The namespace from which to provide class instances. An empty string indicates this
     * container should resolve classes from any namespace.
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     *
     * @return $this
     * @psalm-external-mutation-free
     */
    public function addSingletonNamespace(string $namespace, ?callable $factory = null): static;

    /**
     * Indicates the container should provide singleton instances of classes inheriting from the given interface or base
     * class using the specified factory method.
     *
     * @param class-string $interfaceName The fully qualified name of the interface or base class
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     *
     * @return $this
     */
    public function addSingletonInterface(string $interfaceName, ?callable $factory = null): static;

    /**
     * Indicates the container should provide singleton instances of classes with the given attribute using the
     * specified factory method.
     *
     * @param class-string $attributeName The fully qualified name of the attribute that must be present to enable this
     * container for a class
     * @param callable|null $factory [optional] The factory to use for acquiring instances of classes. The first
     * argument will be the name of the class. The second argument will be an instance of the attribute attached to the
     * class. Additional arguments can be provided from this container's {@see Injector}. If no factory is provided, a
     * default factory that directly instantiates the class will be used.
     * <code>
     * callable&lt;TClass, TAttr&gt;(class-string&lt;TClass&gt; $className, TAttr $attr, ...): TClass
     * </code>
     *
     * @return $this
     */
    public function addSingletonAttribute(string $attributeName, ?callable $factory = null): static;
}
