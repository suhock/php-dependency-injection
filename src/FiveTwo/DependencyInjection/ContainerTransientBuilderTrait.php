<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\ClassInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\ClosureInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
use FiveTwo\DependencyInjection\Instantiation\ImplementationInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\TransientStrategy;

/**
 * Implements convenience methods for adding transients to a container.
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
     * @param InstanceFactory<TClass> $instanceFactory
     *
     * @return $this
     */
    public function addTransient(string $className, InstanceFactory $instanceFactory): static
    {
        $this->add($className, new TransientStrategy($className), $instanceFactory);

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addTransientClass(string $className): static
    {
        $this->addTransient(
            $className,
            new ClassInstanceFactory($className, $this->getInjector())
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
    public function addTransientFactory(string $className, callable $factory): static
    {
        $this->addTransient(
            $className,
            new ClosureInstanceFactory($className, $factory(...), $this->getInjector())
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
        $this->addTransientContainer(new NamespaceContainer($namespace, $this->injector, $factory));

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
        $this->addTransientContainer(new ImplementationContainer($interfaceName, $this->injector, $factory));

        return $this;
    }
}
