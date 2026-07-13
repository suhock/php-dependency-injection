<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use Suhock\DependencyInjection\Descriptor\Descriptor;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use UnitEnum;

/**
 * Default implementation for {@see ContainerBuilderInterface}.
 */
trait ContainerBuilderTrait
{
    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     */
    abstract protected function addDescriptor(Descriptor $descriptor);

    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     */
    abstract protected function addKeyedDescriptor(Descriptor $descriptor, string|UnitEnum $key);

    /**
     * @param class-string $className
     */
    abstract protected function removeDescriptor(string $className, string|UnitEnum|null $key): void;

    /**
     * Adds an instance provider with a lifetime strategy to the container for a given class.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The class name of the service to add
     * @param LifetimeStrategy<TClass> $lifetimeStrategy The lifetime strategy to use to manage instances
     * @param InstanceProviderInterface<TClass> $instanceProvider The instance provider to use to create new instances
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     * service; pass false when their disposal is the responsibility of something outside the container
     *
     * @return $this
     */
    public function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true
    ): static {
        $this->addDescriptor(new Descriptor($className, $lifetimeStrategy, $instanceProvider, $shouldDispose));

        return $this;
    }

    /**
     * Adds a keyed instance provider with a lifetime strategy to the container for a given class.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The class name of the service to add
     * @param string|UnitEnum $key The key of the service
     * @param LifetimeStrategy<TClass> $lifetimeStrategy The lifetime strategy to use to manage instances
     * @param InstanceProviderInterface<TClass> $instanceProvider The instance provider to use to create new instances
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     * service; pass false when their disposal is the responsibility of something outside the container
     *
     * @return $this
     */
    public function addKeyed(
        string $className,
        string|UnitEnum $key,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true
    ): static {
        $this->addKeyedDescriptor(
            new Descriptor($className, $lifetimeStrategy, $instanceProvider, $shouldDispose),
            $key
        );

        return $this;
    }

    /**
     * @param class-string $className The class name of the service to remove
     * @param string|UnitEnum|null $key [optional] The key of the service to remove, or null for the unkeyed service
     *
     * @return $this
     */
    public function remove(string $className, string|UnitEnum|null $key = null): static
    {
        $this->removeDescriptor($className, $key);

        return $this;
    }

    /**
     * @param callable(static):mixed $configure
     *
     * @return $this
     */
    public function configure(callable $configure): static
    {
        $configure($this);

        return $this;
    }
}
