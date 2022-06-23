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

use FiveTwo\DependencyInjection\Lifetime\TransientStrategy;
use FiveTwo\DependencyInjection\Provision\ClassInstanceProvider;
use FiveTwo\DependencyInjection\Provision\ClosureInstanceProvider;
use FiveTwo\DependencyInjection\Provision\ImplementationException;
use FiveTwo\DependencyInjection\Provision\ImplementationInstanceProvider;
use FiveTwo\DependencyInjection\Provision\InstanceProvider;

/**
 * Default implementation for {@see ContainerTransientBuilderInterface}. Classes using this trait must implement
 * {@see ContainerBuilderInterface}.
 *
 * @psalm-require-implements ContainerBuilderInterface
 * @psalm-external-mutation-free
 */
trait ContainerTransientBuilderTrait
{
    abstract protected function getInjector(): InjectorInterface;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProvider<TClass> $instanceProvider
     *
     * @return $this
     * @psalm-external-mutation-free
     */
    public function addTransient(string $className, InstanceProvider $instanceProvider): static
    {
        $this->add($className, new TransientStrategy($className), $instanceProvider);

        return $this;
    }

    /**
     * @param class-string $className
     * @param callable|null $mutator
     *
     * @return $this
     * @throws ImplementationException
     * @psalm-external-mutation-free
     */
    public function addTransientClass(string $className, ?callable $mutator = null): static
    {
        $this->addTransient(
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
     * @psalm-external-mutation-free
     */
    public function addTransientImplementation(string $className, string $implementationClassName): static
    {
        $this->addTransient(
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
     * @psalm-external-mutation-free
     */
    public function addTransientFactory(string $className, callable $factory): static
    {
        $this->addTransient(
            $className,
            new ClosureInstanceProvider($className, $factory(...), $this->getInjector())
        );

        return $this;
    }

    /**
     * @inheritDoc
     * @psalm-external-mutation-free
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
     * @param callable|null $factory
     *
     * @return $this
     * @psalm-external-mutation-free
     */
    public function addTransientNamespace(string $namespace, ?callable $factory = null): static
    {
        $this->addTransientContainer(new NamespaceContainer($namespace, $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @param class-string $interfaceName
     * @param callable|null $factory
     *
     * @return $this
     * @psalm-external-mutation-free
     */
    public function addTransientInterface(string $interfaceName, ?callable $factory = null): static
    {
        $this->addTransientContainer(new InterfaceContainer($interfaceName, $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @param class-string $attributeName
     * @param callable|null $factory
     *
     * @return $this
     * @psalm-external-mutation-free
     */
    public function addTransientAttribute(string $attributeName, ?callable $factory = null): static
    {
        $this->addTransientContainer(new AttributeContainer($attributeName, $this->getInjector(), $factory));

        return $this;
    }
}
