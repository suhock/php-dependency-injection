<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\InstanceTypeException;

/**
 * Provides convenience methods for adding singleton factories to a container.
 */
interface ContainerSingletonBuilderInterface
{
    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceFactory<TClass> $instanceFactory
     *
     * @return $this
     */
    public function addSingleton(string $className, InstanceFactory $instanceFactory): static;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addSingletonClass(string $className): static;

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
    public function addSingletonImplementation(string $className, string $implementationClassName): static;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param callable():TClass $factory
     *
     * @return $this
     * @psalm-param callable(...):(TClass|null) $factory
     */
    public function addSingletonFactory(string $className, callable $factory): static;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param TClass|null $instance
     *
     * @return $this
     * @throws InstanceTypeException
     */
    public function addSingletonInstance(string $className, ?object $instance): static;

    /**
     * @param ContainerInterface $container
     *
     * @return static
     */
    public function addSingletonContainer(ContainerInterface $container): static;

    /**
     * @param string $namespace
     *
     * @return $this
     */
    public function addSingletonNamespace(string $namespace): static;

    /**
     * @template TInterface of object
     *
     * @param class-string<TInterface> $interfaceName
     *
     * @return $this
     */
    public function addSingletonInterface(string $interfaceName): static;
}
