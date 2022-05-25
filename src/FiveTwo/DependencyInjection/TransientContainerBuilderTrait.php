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
 * Provides convenience methods for adding transients to a container.
 *
 * @psalm-require-implements ContainerBuilderInterface
 * @psalm-require-implements TransientContainerBuilderInterface
 */
trait TransientContainerBuilderTrait
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
    public function addTransient(string $className, InstanceFactory $instanceFactory): static
    {
        $this->add($className, new TransientStrategy($className), $instanceFactory);

        return $this;
    }

    /**
     * @template TDependency
     * @template TImplementation of TDependency
     *
     * @param class-string<TDependency> $className
     * @param class-string<TImplementation>|'' $implementationClassName
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addTransientClass(string $className, string $implementationClassName = ''): static
    {
        $this->addTransient(
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
    public function addTransientFactory(string $className, callable $factory): static
    {
        $this->addTransient(
            $className,
            new ClosureInstanceFactory($className, $factory(...), $this->getInjector())
        );

        return $this;
    }

    /**
     * @param DependencyContainerInterface $container
     *
     * @return static
     */
    public function addTransientContainer(DependencyContainerInterface $container): static
    {
        $this->addContainer($container, fn(string $className) => new TransientStrategy($className));

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
        $this->addTransientContainer(new NamespaceContainer(
            $namespace,
            $factory !== null ?
                $factory(...) :
                /** @param class-string $className */
                fn(string $className) => $this->getInjector()->instantiate($className),
            $this->injector
        ));

        return $this;
    }

    /**
     * @template TInterface
     *
     * @param class-string<TInterface> $interfaceName
     * @param null|callable(class-string<TInterface>):TInterface|null $factory
     *
     * @return $this
     */
    public function addTransientInterface(string $interfaceName, ?callable $factory = null): static
    {
        $this->addTransientContainer(new ImplementationContainer(
            $interfaceName,
            $factory !== null ?
                $factory(...) :
                /** @param class-string $className */
                fn(string $className) => $this->getInjector()->instantiate($className),
            $this->injector
        ));

        return $this;
    }
}
