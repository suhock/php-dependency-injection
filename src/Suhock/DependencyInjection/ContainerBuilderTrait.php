<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use Suhock\DependencyInjection\Provision\InstanceProviderInterface;

/**
 * Default implementation for {@see ContainerBuilderInterface}.
 */
trait ContainerBuilderTrait
{
    abstract protected function getInjector(): InjectorInterface;

    /**
     * @template TClass of object
     *
     * @param Descriptor<TClass> $descriptor
     */
    abstract protected function addDescriptor(Descriptor $descriptor);

    abstract protected function addContainerDescriptor(ContainerDescriptor $descriptor);

    /**
     * Adds an instance provider with a lifetime strategy to the container for a given class.
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to add
     * @param LifetimeStrategy<TClass> $lifetimeStrategy The lifetime strategy to use to manage instances of the class
     * @param InstanceProviderInterface<TClass> $instanceProvider The instance provider to use to create new instances of the
     * class
     *
     * @return $this
     */
    public function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider
    ): static {
        $this->addDescriptor(new Descriptor($className, $lifetimeStrategy, $instanceProvider));

        return $this;
    }

    /**
     * @param ContainerInterface $container The nested container to add
     * @param callable(class-string):LifetimeStrategy $lifetimeStrategyFactory A factory method for generating lifetime
     * strategies to manage instances within the container being built
     *
     * @phpstan-ignore-next-line PHPStan does not support callable-level generics but complains that LifetimeStrategy
     * does not have its generic type specified
     */
    public function addContainer(ContainerInterface $container, callable $lifetimeStrategyFactory): static
    {
        $this->addContainerDescriptor(
            new ContainerDescriptor($container, $this->getInjector(), $lifetimeStrategyFactory(...))
        );

        return $this;
    }

    /**
     * @param callable(static):mixed $builder
     *
     * @return $this
     */
    public function build(callable $builder): static
    {
        /**
         * PHPStan doesn't think Container implements ContainerBuilderInterface?
         * @phpstan-ignore-next-line
         */
        $builder($this);

        return $this;
    }
}
