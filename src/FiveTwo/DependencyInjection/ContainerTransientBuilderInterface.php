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

use FiveTwo\DependencyInjection\InstanceProvision\ImplementationException;
use FiveTwo\DependencyInjection\InstanceProvision\InstaceProvider;

/**
 * Interface for adding transient factories to a container.
 */
interface ContainerTransientBuilderInterface
{
    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstaceProvider<TClass> $instanceProvider
     *
     * @return $this
     */
    public function addTransient(string $className, InstaceProvider $instanceProvider): static;

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
    public function addTransientClass(string $className, ?callable $mutator = null): static;

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
