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

use FiveTwo\DependencyInjection\Provision\ImplementationException;
use FiveTwo\DependencyInjection\Provision\InstanceProvider;

/**
 * Interface for adding transient factories to a container.
 */
interface ContainerTransientBuilderInterface
{
    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProvider<TClass> $instanceProvider
     *
     * @return $this
     */
    public function addTransient(string $className, InstanceProvider $instanceProvider): static;

    /**
     * @param class-string $className
     * @param callable|null $mutator
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
     * @param class-string $className
     * @param callable $factory
     *
     * @return $this
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
     * @param callable|null $factory
     *
     * @return $this
     */
    public function addTransientNamespace(string $namespace, ?callable $factory = null): static;

    /**
     * @param class-string $interfaceName
     * @param callable|null $factory
     *
     * @return $this
     */
    public function addTransientInterface(string $interfaceName, ?callable $factory = null): static;

    /**
     * @param class-string $attributeName
     * @param callable|null $factory
     *
     * @return $this
     */
    public function addTransientAttribute(string $attributeName, ?callable $factory = null): static;
}
