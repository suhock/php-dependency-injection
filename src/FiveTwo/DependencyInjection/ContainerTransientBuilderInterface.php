<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;

/**
 * Provides convenience methods for adding transients to a container.
 */
interface ContainerTransientBuilderInterface
{
    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceFactory<TClass> $instanceFactory
     *
     * @return $this
     */
    public function addTransient(string $className, InstanceFactory $instanceFactory): static;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     *
     * @return $this
     * @throws ImplementationException
     */
    public function addTransientClass(string $className): static;

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
    public function addTransientImplementation(string $className, string $implementationClassName): static;

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param callable():TClass $factory
     *
     * @return $this
     * @psalm-param callable(...):(TClass|null) $factory
     */
    public function addTransientFactory(string $className, callable $factory): static;

    /**
     * @param ContainerInterface $container
     *
     * @return static
     */
    public function addTransientContainer(ContainerInterface $container): static;

    /**
     * @param string $namespace
     *
     * @return $this
     */
    public function addTransientNamespace(string $namespace): static;

    /**
     * @template TInterface of object
     *
     * @param class-string<TInterface> $interfaceName
     *
     * @return $this
     */
    public function addTransientInterface(string $interfaceName): static;
}
