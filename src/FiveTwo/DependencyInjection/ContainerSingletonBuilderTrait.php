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

use FiveTwo\DependencyInjection\Instantiation\ClassInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\ClosureInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
use FiveTwo\DependencyInjection\Instantiation\ImplementationInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\InstanceTypeException;
use FiveTwo\DependencyInjection\Instantiation\ObjectInstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\SingletonStrategy;

/**
 * Default implementation for {@see ContainerSingletonBuilderInterface}. Classes using this trait must implement
 * implement {@see ContainerBuilderInterface}.
 *
 * @psalm-require-implements ContainerBuilderInterface
 * @psalm-require-implements ContainerSingletonBuilderInterface
 */
trait ContainerSingletonBuilderTrait
{
    abstract protected function getInjector(): InjectorInterface;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceFactory<TClass> $instanceFactory
     *
     * @return $this
     */
    public function addSingleton(string $className, InstanceFactory $instanceFactory): static
    {
        $this->add($className, new SingletonStrategy($className), $instanceFactory);

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param null|callable(TClass):void $mutator
     * @psalm-param null|callable(TClass, mixed...):void $mutator
     * @phpstan-param null|callable(TClass, mixed...):void $mutator
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addSingletonClass(string $className, ?callable $mutator = null): static
    {
        $this->addSingleton(
            $className,
            new ClassInstanceFactory($className, $this->getInjector(), $mutator !== null ? $mutator(...) : null)
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
            new ImplementationInstanceFactory($className, $implementationClassName, $this)
        );

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param callable $factory
     * @psalm-param callable(mixed ...$params):(TClass|null) $factory
     * @phpstan-param callable(mixed ...$params):(TClass|null) $factory
     *
     * @return $this
     */
    public function addSingletonFactory(string $className, callable $factory): static
    {
        $this->addSingleton(
            $className,
            new ClosureInstanceFactory($className, $factory(...), $this->getInjector())
        );

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param TClass|null $instance
     *
     * @return $this
     * @throws InstanceTypeException
     */
    public function addSingletonInstance(string $className, ?object $instance): static
    {
        $this->addSingleton(
            $className,
            new ObjectInstanceFactory($className, $instance)
        );

        return $this;
    }

    /**
     * @param ContainerInterface $container
     *
     * @return static
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
     * @param string $namespace
     * @param null|callable(class-string):(object|null) $factory
     *
     * @return $this
     */
    public function addSingletonNamespace(string $namespace, ?callable $factory = null): static
    {
        $this->addSingletonContainer(new NamespaceContainer($namespace, $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @template TInterface of object
     *
     * @param class-string<TInterface> $interfaceName
     * @param null|callable(class-string<TInterface>):(TInterface|null) $factory
     *
     * @return $this
     */
    public function addSingletonInterface(string $interfaceName, ?callable $factory = null): static
    {
        /** @psalm-suppress ArgumentTypeCoercion argument types are the same... */
        $this->addSingletonContainer(new InterfaceContainer($interfaceName, $this->getInjector(), $factory));

        return $this;
    }
}
