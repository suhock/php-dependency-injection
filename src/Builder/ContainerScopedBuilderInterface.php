<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\InstanceProvider\ImplementationException;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
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
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param class-string<TImplementation>|object|null $source
     * - If null, indicates that the container should provide an instance of the given class by autowiring its
     *   constructor.
     * - If a string, indicates that the container should provide an instance of the given class by retrieving an
     *   instance of the specified implementation class from the container. The container must also specify how to
     *   resolve the implementation class.
     * - If a closure, indicates that the container should provide an instance of the given class by calling the
     *   closure as a factory. Any closure parameters will be injected. To autowire the class and then mutate the
     *   constructed instance, use {@see addScopedClass()} or {@see addKeyedScopedClass()} instead.
     *
     * @return $this
     */
    public function addScoped(string $className, string|object|null $source = null): static;

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param class-string<TImplementation>|object|null $source See {@see addScoped()}
     *
     * @return $this
     */
    public function addKeyedScoped(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null
    ): static;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param InstanceProviderInterface<TClass> $instanceProvider
     *
     * @return $this
     */
    public function addScopedInstanceProvider(
        string $className,
        InstanceProviderInterface $instanceProvider
    ): static;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param InstanceProviderInterface<TClass> $instanceProvider
     *
     * @return $this
     */
    public function addKeyedScopedInstanceProvider(
        string $className,
        string|UnitEnum $key,
        InstanceProviderInterface $instanceProvider
    ): static;

    /**
     * Indicates that the container should provide a per-scope instance of the given class by autowiring its
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
     */
    public function addScopedClass(string $className, ?callable $mutator = null): static;

    /**
     * Indicates that the container should provide a per-scope instance of the given class under the given key by
     * autowiring its constructor. An optional mutator function can be specified to perform additional initialization
     * on the constructed object.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param string|UnitEnum $key The key to add the service under
     * @param callable|null $mutator [optional] This function will be called after an instance of the class has been
     * created. The class instance will be provided as the first parameter. Any additional parameters will be injected.
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addKeyedScopedClass(
        string $className,
        string|UnitEnum $key,
        ?callable $mutator = null
    ): static;

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
     * or extends {@see $className}.
     *
     * @return $this
     * @throws ImplementationException If the implementation class is not a subclass of the class being added
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
     * implements or extends {@see $className}.
     *
     * @return $this
     * @throws ImplementationException If the implementation class is not a subclass of the class being added
     */
    public function addKeyedScopedImplementation(
        string $className,
        string|UnitEnum $key,
        string $implementationClassName
    ): static;

    /**
     * Indicates that the container should provide a per-scope instance of the given class by calling the specified
     * factory method.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The fully qualified name of the class to add
     * @param callable $factory A factory method that returns an instance of the class specified by {@see $className}.
     * Any method parameters will be injected from the resolving scope.
     *
     * @return $this
     */
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
     * Any method parameters will be injected from the resolving scope.
     *
     * @return $this
     */
    public function addKeyedScopedFactory(string $className, string|UnitEnum $key, callable $factory): static;

    /**
     * @return $this
     */
    public function addScopedContainer(ContainerInterface $container): static;

    /**
     * Indicates the container should provide per-scope instances of classes within the given namespace using the
     * specified factory method.
     *
     * @param string $namespace The namespace from which to provide class instances. An empty string indicates this
     * container should resolve classes from any namespace.
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     *
     * @return $this
     */
    public function addScopedNamespace(string $namespace, ?callable $factory = null): static;

    /**
     * Indicates the container should provide per-scope instances of classes inheriting from the given interface or base
     * class using the specified factory method.
     *
     * @param class-string $interfaceName The fully qualified name of the interface or base class
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     *
     * @return $this
     */
    public function addScopedInterface(string $interfaceName, ?callable $factory = null): static;

    /**
     * Indicates the container should provide per-scope instances of classes with the given attribute using the
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
    public function addScopedAttribute(string $attributeName, ?callable $factory = null): static;
}
