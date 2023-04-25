<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022-2023 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Lifetime\TransientStrategy;
use Suhock\DependencyInjection\Provision\ClassInstanceProvider;
use Suhock\DependencyInjection\Provision\ClosureInstanceProvider;
use Suhock\DependencyInjection\Provision\ImplementationInstanceProvider;
use Suhock\DependencyInjection\Provision\InstanceProvider;

/**
 * Default implementation for {@see ContainerTransientBuilderInterface}. Classes using this trait must implement
 * {@see ContainerBuilderInterface} and the {@see getInjector()} function.
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
     */
    public function addTransient(string $className, InstanceProvider $instanceProvider): static
    {
        $this->add($className, new TransientStrategy($className), $instanceProvider);

        return $this;
    }

    /**
     * @param class-string $className
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
     * @param class-string<TClass> $className
     * @param class-string<TImplementation> $implementationClassName
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
     */
    public function addTransientFactory(string $className, callable $factory): static
    {
        $this->addTransient(
            $className,
            new ClosureInstanceProvider($className, $factory(...), $this->getInjector())
        );

        return $this;
    }

    public function addTransientContainer(ContainerInterface $container): static
    {
        $this->addContainer(
            $container,
            /** @param class-string $className */
            fn (string $className) => new TransientStrategy($className)
        );

        return $this;
    }

    public function addTransientNamespace(string $namespace, ?callable $factory = null): static
    {
        $this->addTransientContainer(new NamespaceContainer($namespace, $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @param class-string $interfaceName
     */
    public function addTransientInterface(string $interfaceName, ?callable $factory = null): static
    {
        $this->addTransientContainer(new InterfaceContainer($interfaceName, $this->getInjector(), $factory));

        return $this;
    }

    /**
     * @param class-string $attributeName
     */
    public function addTransientAttribute(string $attributeName, ?callable $factory = null): static
    {
        $this->addTransientContainer(new AttributeContainer($attributeName, $this->getInjector(), $factory));

        return $this;
    }
}
