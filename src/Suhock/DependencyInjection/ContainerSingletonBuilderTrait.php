<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Closure;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use Suhock\DependencyInjection\Provision\InstanceProviderFactory;
use Suhock\DependencyInjection\Provision\InstanceProviderInterface;

/**
 * Default implementation for {@see ContainerSingletonBuilderInterface}. Classes using this trait must implement
 * {@see ContainerBuilderInterface} and the {@see getInjector()} function.
 *
 * @psalm-require-implements ContainerBuilderInterface
 * @psalm-external-mutation-free
 */
trait ContainerSingletonBuilderTrait
{
    /**
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className
     * @param class-string<TImplementation>|TClass|Closure|null $source
     *
     * @return $this
     */
    public function addSingleton(string $className, string|object|null $source = null): static
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createInstanceProvider($this->getInjector(), $this, $className, $source)
        );

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     *
     * @return $this
     */
    public function addSingletonInstanceProvider(string $className, InstanceProviderInterface $instanceProvider): static
    {
        $this->add($className, new SingletonStrategy($className), $instanceProvider);

        return $this;
    }

    abstract protected function getInjector(): InjectorInterface;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     */
    public function addSingletonClass(string $className, ?callable $mutator = null): static
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createClassInstanceProvider($this->getInjector(), $className, $mutator)
        );

        return $this;
    }

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     * @psalm-param class-string<TClass> $className
     * @psalm-param class-string<TImplementation> $implementationClassName
     */
    public function addSingletonImplementation(string $className, string $implementationClassName): static
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createImplementationInstanceProvider($this, $className, $implementationClassName)
        );

        return $this;
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
            InstanceProviderFactory::createClosureInstanceProvider($this->getInjector(), $className, $factory(...))
        );

        return $this;
    }

    /**
     * @template TClass of object
     * @template TInstance of TClass
     *
     * @param class-string<TClass> $className
     * @param TInstance $instance
     *
     * @return $this
     */
    public function addSingletonInstance(string $className, object $instance): static
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createObjectInstanceProvider($className, $instance)
        );

        return $this;
    }

    public function addSingletonContainer(ContainerInterface $container): static
    {
        $this->addContainer(
            $container, /** @param class-string $className */
            fn (string $className) => new SingletonStrategy($className)
        );

        return $this;
    }

    public function addSingletonNamespace(string $namespace, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new NamespaceContainer($namespace, $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @param class-string $interfaceName
     */
    public function addSingletonInterface(string $interfaceName, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new InterfaceContainer($interfaceName, $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @param class-string $attributeName
     */
    public function addSingletonAttribute(string $attributeName, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new AttributeContainer($attributeName, $this->getInjector(), $factory));

        return $this;
    }
}
