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
use Suhock\DependencyInjection\AttributeContainer;
use Suhock\DependencyInjection\ContainerInterface;
use Suhock\DependencyInjection\InjectorInterface;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderFactory;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\InterfaceContainer;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use Suhock\DependencyInjection\NamespaceContainer;
use UnitEnum;

/**
 * Default implementation for {@see ContainerSingletonBuilderInterface}. Classes using this trait must implement
 * {@see ContainerBuilderInterface} and the {@see getInjector()} function.
 */
trait ContainerSingletonBuilderTrait
{
    abstract protected function getInjector(): InjectorInterface;

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className
     * @param class-string<TImplementation>|TClass|Closure|null $source
     *
     * @return $this
     */
    // @phpstan-ignore method.childParameterType (false positive on templated builder generics)
    public function addSingleton(string $className, string|object|null $source = null): static
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createInstanceProvider($className, $source)
        );

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|TClass|Closure|null $source
     */
    public function addKeyedSingleton(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null
    ): static {
        return $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createInstanceProvider($className, $source)
        );
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     * service
     *
     * @return $this
     */
    public function addSingletonInstanceProvider(
        string $className,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true
    ): static {
        $this->add($className, new SingletonStrategy($className), $instanceProvider, $shouldDispose);

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     * service
     *
     * @return $this
     */
    public function addKeyedSingletonInstanceProvider(
        string $className,
        string|UnitEnum $key,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true
    ): static {
        return $this->addKeyed($className, $key, new SingletonStrategy($className), $instanceProvider, $shouldDispose);
    }

    /**
     * @inheritDoc
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param Closure|callable-string|null $mutator
     */
    // @phpstan-ignore method.childParameterType (false positive on templated builder generics)
    public function addSingletonClass(string $className, ?callable $mutator = null): static
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createClassInstanceProvider($className, $mutator)
        );

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param Closure|callable-string|null $mutator
     */
    // @phpstan-ignore method.childParameterType (false positive on templated builder generics)
    public function addKeyedSingletonClass(
        string $className,
        string|UnitEnum $key,
        ?callable $mutator = null
    ): static {
        return $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClassInstanceProvider($className, $mutator)
        );
    }

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     */
    public function addSingletonImplementation(string $className, string $implementationClassName): static
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createImplementationInstanceProvider($className, $implementationClassName)
        );

        return $this;
    }

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     */
    public function addKeyedSingletonImplementation(
        string $className,
        string|UnitEnum $key,
        string $implementationClassName
    ): static {
        return $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createImplementationInstanceProvider($className, $implementationClassName)
        );
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     */
    public function addSingletonFactory(string $className, callable $factory): static
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...))
        );

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     */
    public function addKeyedSingletonFactory(string $className, string|UnitEnum $key, callable $factory): static
    {
        return $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...))
        );
    }

    /**
     * @template TClass of object
     * @template TInstance of TClass
     *
     * @param class-string<TClass> $className
     * @param TInstance $instance
     * @param bool $shouldDispose Whether the container should dispose the instance, if it implements
     * {@see \Suhock\DependencyInjection\DisposableInterface}, when the container is disposed. Pass false to retain
     * disposal responsibility yourself, e.g. when the instance is shared with code outside the container.
     *
     * @return $this
     */
    // @phpstan-ignore method.childParameterType (false positive on templated builder generics)
    public function addSingletonInstance(string $className, object $instance, bool $shouldDispose = true): static
    {
        return $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createObjectInstanceProvider($className, $instance),
            $shouldDispose
        );
    }

    /**
     * @template TClass of object
     * @template TInstance of TClass
     *
     * @param class-string<TClass> $className
     * @param TInstance $instance
     * @param bool $shouldDispose Whether the container should dispose the instance, if it implements
     * {@see \Suhock\DependencyInjection\DisposableInterface}, when the container is disposed. Pass false to retain
     * disposal responsibility yourself, e.g. when the instance is shared with code outside the container.
     *
     * @return $this
     */
    // @phpstan-ignore method.childParameterType (false positive on templated builder generics)
    public function addKeyedSingletonInstance(
        string $className,
        string|UnitEnum $key,
        object $instance,
        bool $shouldDispose = true
    ): static {
        return $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createObjectInstanceProvider($className, $instance),
            $shouldDispose
        );
    }

    public function addSingletonContainer(ContainerInterface $container): static
    {
        $this->addContainer(
            $container,
            /** @param class-string $className */
            fn (string $className) => new SingletonStrategy($className)
        );

        return $this;
    }

    public function addSingletonNamespace(string $namespace, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new NamespaceContainer($namespace, fn () => $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @param class-string $interfaceName
     */
    public function addSingletonInterface(string $interfaceName, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new InterfaceContainer($interfaceName, fn () => $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @param class-string $attributeName
     */
    public function addSingletonAttribute(string $attributeName, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new AttributeContainer($attributeName, fn () => $this->getInjector(), $factory));

        return $this;
    }
}
