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
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param InstanceFactory<TDependency> $instanceFactory
     *
     * @return $this
     */
    public function addTransient(string $className, InstanceFactory $instanceFactory): static;

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
    public function addTransientClass(string $className, string $implementationClassName = ''): static;

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param callable():TDependency $factory
     *
     * @return $this
     * @psalm-param callable(...):(TDependency|null) $factory
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
     * @template TInterface
     *
     * @param class-string<TInterface> $interfaceName
     *
     * @return $this
     */
    public function addTransientInterface(string $interfaceName): static;
}
