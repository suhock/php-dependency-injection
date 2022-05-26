<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\ClassInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\ClosureInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\DependencyTypeException;
use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
use FiveTwo\DependencyInjection\Instantiation\ImplementationInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\ObjectInstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\SingletonStrategy;

/**
 * Provides convenience methods for adding singletons to a container.
 *
 * @psalm-require-implements ContainerBuilderInterface
 * @psalm-require-implements SingletonContainerBuilderInterface
 */
trait SingletonContainerBuilderTrait
{
    protected abstract function getInjector(): DependencyInjectorInterface;

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param InstanceFactory<TDependency> $instanceFactory
     *
     * @return $this
     */
    public function addSingleton(string $className, InstanceFactory $instanceFactory): static
    {
        $this->add($className, new SingletonStrategy($className), $instanceFactory);

        return $this;
    }

    /**
     * @template TDependency
     * @template TImplementation of TDependency
     *
     * @param class-string<TDependency> $className
     * @param class-string<TImplementation> $implementationClassName
     *
     * @return $this
     * @throws ImplementationException
     * @psalm-param class-string<TImplementation>|'' $implementationClassName
     */
    public function addSingletonClass(string $className, string $implementationClassName = ''): static
    {
        $this->addSingleton(
            $className,
            ($implementationClassName === $className || $implementationClassName === '') ?
                new ClassInstanceFactory($className, $this->getInjector()) :
                new ImplementationInstanceFactory($className, $implementationClassName, $this)
        );

        return $this;
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param callable():TDependency $factory
     *
     * @return $this
     * @psalm-param callable(...):(TDependency|null) $factory
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
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param TDependency|null $instance
     *
     * @return $this
     * @throws DependencyTypeException
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
     * @param DependencyContainerInterface $container
     *
     * @return static
     */
    public function addSingletonContainer(DependencyContainerInterface $container): static
    {
        $this->addContainer($container, fn(string $className) => new SingletonStrategy($className));

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
        $this->addSingletonContainer(new NamespaceContainer($namespace, $this->injector, $factory));

        return $this;
    }

    /**
     * @template TInterface
     *
     * @param class-string<TInterface> $interfaceName
     * @param null|callable(class-string<TInterface>):(TInterface|null) $factory
     *
     * @return $this
     */
    public function addSingletonInterface(string $interfaceName, ?callable $factory = null): static
    {
        /** @psalm-suppress ArgumentTypeCoercion argument types are the same... */
        $this->addSingletonContainer(new ImplementationContainer($interfaceName, $this->injector, $factory));

        return $this;
    }
}
