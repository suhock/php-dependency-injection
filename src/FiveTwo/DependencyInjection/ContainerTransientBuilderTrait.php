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

use FiveTwo\DependencyInjection\InstanceProvision\ClassInstaceProvider;
use FiveTwo\DependencyInjection\InstanceProvision\ClosureInstaceProvider;
use FiveTwo\DependencyInjection\InstanceProvision\ImplementationException;
use FiveTwo\DependencyInjection\InstanceProvision\ImplementationInstaceProvider;
use FiveTwo\DependencyInjection\InstanceProvision\InstaceProvider;
use FiveTwo\DependencyInjection\Lifetime\TransientStrategy;

/**
 * Default implementation for {@see ContainerTransientBuilderInterface}. Classes using this trait must implement
 * {@see ContainerBuilderInterface}.
 *
 * @psalm-require-implements ContainerBuilderInterface
 * @psalm-require-implements ContainerTransientBuilderInterface
 */
trait ContainerTransientBuilderTrait
{
    abstract protected function getInjector(): InjectorInterface;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstaceProvider<TClass> $instanceProvider
     *
     * @return $this
     */
    public function addTransient(string $className, InstaceProvider $instanceProvider): static
    {
        $this->add($className, new TransientStrategy($className), $instanceProvider);

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param null|callable $mutator
     * @psalm-param null|callable(TClass, mixed ...):void $mutator
     * @phpstan-param null|callable(TClass, mixed ...):void $mutator
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addTransientClass(string $className, ?callable $mutator = null): static
    {
        $this->addTransient(
            $className,
            new ClassInstaceProvider($className, $this->getInjector(), $mutator !== null ? $mutator(...) : null)
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
    public function addTransientImplementation(string $className, string $implementationClassName): static
    {
        $this->addTransient(
            $className,
            new ImplementationInstaceProvider($className, $implementationClassName, $this)
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
    public function addTransientFactory(string $className, callable $factory): static
    {
        $this->addTransient(
            $className,
            new ClosureInstaceProvider($className, $factory(...), $this->getInjector())
        );

        return $this;
    }

    /**
     * @param ContainerInterface $container
     *
     * @return static
     */
    public function addTransientContainer(ContainerInterface $container): static
    {
        $this->addContainer(
            $container,
            /** @param class-string $className */
            fn (string $className) => new TransientStrategy($className)
        );

        return $this;
    }

    /**
     * @param string $namespace
     * @param null|callable(class-string):object|null $factory
     *
     * @return $this
     */
    public function addTransientNamespace(string $namespace, ?callable $factory = null): static
    {
        $this->addTransientContainer(new NamespaceContainer($namespace, $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @template TInterface of object
     *
     * @param class-string<TInterface> $interfaceName
     * @param null|callable(class-string<TInterface>):TInterface|null $factory
     *
     * @return $this
     */
    public function addTransientInterface(string $interfaceName, ?callable $factory = null): static
    {
        /** @psalm-suppress ArgumentTypeCoercion argument types are the same... */
        $this->addTransientContainer(new InterfaceContainer($interfaceName, $this->getInjector(), $factory));

        return $this;
    }
}
