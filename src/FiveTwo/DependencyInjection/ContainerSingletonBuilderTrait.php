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

use FiveTwo\DependencyInjection\Lifetime\SingletonStrategy;
use FiveTwo\DependencyInjection\Provision\ClassInstanceProvider;
use FiveTwo\DependencyInjection\Provision\ClosureInstanceProvider;
use FiveTwo\DependencyInjection\Provision\ImplementationException;
use FiveTwo\DependencyInjection\Provision\ImplementationInstanceProvider;
use FiveTwo\DependencyInjection\Provision\InstanceProvider;
use FiveTwo\DependencyInjection\Provision\InstanceTypeException;
use FiveTwo\DependencyInjection\Provision\ObjectInstanceProvider;

/**
 * Default implementation for {@see ContainerSingletonBuilderInterface}. Classes using this trait must implement
 * implement {@see ContainerBuilderInterface}.
 *
 * @psalm-require-implements ContainerBuilderInterface
 * @psalm-external-mutation-free
 */
trait ContainerSingletonBuilderTrait
{
    abstract protected function getInjector(): InjectorInterface;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProvider<TClass> $instanceProvider
     *
     * @return $this
     */
    public function addSingleton(string $className, InstanceProvider $instanceProvider): static
    {
        $this->add($className, new SingletonStrategy($className), $instanceProvider);

        return $this;
    }

    /**
     * @param class-string $className
     * @param callable|null $mutator
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addSingletonClass(string $className, ?callable $mutator = null): static
    {
        $this->addSingleton(
            $className,
            new ClassInstanceProvider($className, $this->getInjector(), $mutator)
        );

        return $this;
    }
    /**
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className
     * @param class-string<TImplementation> $implementationClassName
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addSingletonImplementation(string $className, string $implementationClassName): static
    {
        $this->addSingleton(
            $className,
            new ImplementationInstanceProvider($className, $implementationClassName, $this)
        );

        return $this;
    }

    /**
     * @param class-string $className
     * @param callable $factory
     *
     * @return $this
     */
    public function addSingletonFactory(string $className, callable $factory): static
    {
        $this->addSingleton(
            $className,
            new ClosureInstanceProvider($className, $factory(...), $this->getInjector())
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
     * @throws InstanceTypeException
     */
    public function addSingletonInstance(string $className, object $instance): static
    {
        $this->addSingleton(
            $className,
            new ObjectInstanceProvider($className, $instance)
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addSingletonContainer(ContainerInterface $container): static
    {
        $this->addContainer(
            $container,
            /** @param class-string $className */
            fn (string $className) => new SingletonStrategy($className)
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addSingletonNamespace(string $namespace, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new NamespaceContainer($namespace, $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @param class-string $interfaceName
     * @param callable|null $factory
     *
     * @return $this
     */
    public function addSingletonInterface(string $interfaceName, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new InterfaceContainer($interfaceName, $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @param class-string $attributeName
     * @param callable|null $factory
     *
     * @return $this
     */
    public function addSingletonAttribute(string $attributeName, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new AttributeContainer($attributeName, $this->getInjector(), $factory));

        return $this;
    }
}
