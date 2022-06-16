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
use FiveTwo\DependencyInjection\Provision\InstanceTypeException;

/**
 * Interface for adding singleton factories to a container.
 */
interface ContainerSingletonBuilderInterface
{
    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProvider<TClass> $instanceProvider
     *
     * @return $this
     */
    public function addSingleton(string $className, InstanceProvider $instanceProvider): static;

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
    public function addSingletonClass(string $className, ?callable $mutator = null): static;

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
     * @param TClass $instance
     *
     * @return $this
     * @throws InstanceTypeException
     */
    public function addSingletonInstance(string $className, object $instance): static;

    /**
     * @param ContainerInterface $container
     *
     * @return static
     */
    public function addSingletonContainer(ContainerInterface $container): static;

    /**
     * @param string $namespace
     * @param null|callable(class-string):(object|null) $factory
     *
     * @return $this
     */
    public function addSingletonNamespace(string $namespace, ?callable $factory = null): static;

    /**
     * @template TInterface of object
     *
     * @param class-string<TInterface> $interfaceName
     *
     * @return $this
     */
    public function addSingletonInterface(string $interfaceName): static;
}
