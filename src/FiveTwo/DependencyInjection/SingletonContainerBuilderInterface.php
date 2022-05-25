<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\DependencyTypeException;
use FiveTwo\DependencyInjection\Instantiation\ImplementationException;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;

/**
 * Provides convenience methods for adding singletons to a container.
 */
interface SingletonContainerBuilderInterface
{
    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param InstanceFactory<TDependency> $instanceFactory
     *
     * @return $this
     */
    public function addSingleton(string $className, InstanceFactory $instanceFactory): static;

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
    public function addSingletonClass(string $className, string $implementationClassName = ''): static;

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param callable():TDependency $factory
     *
     * @return $this
     * @psalm-param callable(...):(TDependency|null) $factory
     */
    public function addSingletonFactory(string $className, callable $factory): static;

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param TDependency|null $instance
     *
     * @return $this
     * @throws DependencyTypeException
     */
    public function addSingletonInstance(string $className, ?object $instance): static;

    /**
     * @param DependencyContainerInterface $container
     *
     * @return static
     */
    public function addSingletonContainer(DependencyContainerInterface $container): static;

    /**
     * @param string $namespace
     *
     * @return $this
     */
    public function addSingletonNamespace(string $namespace): static;

    /**
     * @template TInterface
     *
     * @param class-string<TInterface> $interfaceName
     *
     * @return $this
     */
    public function addSingletonInterface(string $interfaceName): static;
}