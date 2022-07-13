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
use FiveTwo\DependencyInjection\Provision\ImplementationInstanceProvider;
use FiveTwo\DependencyInjection\Provision\InstanceProvider;
use FiveTwo\DependencyInjection\Provision\ObjectInstanceProvider;

/**
 * Default implementation for {@see ContainerSingletonBuilderInterface}. Classes using this trait must implement
 * {@see ContainerBuilderInterface} and the {@see getInjector()} function.
 *
 * @psalm-require-implements ContainerBuilderInterface
 * @psalm-external-mutation-free
 */
trait ContainerSingletonBuilderTrait
{
    abstract protected function getInjector(): InjectorInterface;

    /**
     * @template TClass of object
     * @psalm-param class-string<TClass> $className
     * @param-param InstanceProvider<TClass> $instanceProvider
     */
    public function addSingleton(string $className, InstanceProvider $instanceProvider): static
    {
        $this->add($className, new SingletonStrategy($className), $instanceProvider);

        return $this;
    }

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
     * @psalm-param class-string<TClass> $className
     * @psalm-param class-string<TImplementation> $implementationClassName
     */
    public function addSingletonImplementation(string $className, string $implementationClassName): static
    {
        $this->addSingleton(
            $className,
            new ImplementationInstanceProvider($className, $implementationClassName, $this)
        );

        return $this;
    }

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
     * @psalm-param class-string<TClass> $className
     * @psalm-param TInstance $instance
     */
    public function addSingletonInstance(string $className, object $instance): static
    {
        $this->addSingleton(
            $className,
            new ObjectInstanceProvider($className, $instance)
        );

        return $this;
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
        $this->addSingletonContainer(new NamespaceContainer($namespace, $this->getInjector(), $factory));

        return $this;
    }

    public function addSingletonInterface(string $interfaceName, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new InterfaceContainer($interfaceName, $this->getInjector(), $factory));

        return $this;
    }

    public function addSingletonAttribute(string $attributeName, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new AttributeContainer($attributeName, $this->getInjector(), $factory));

        return $this;
    }
}
