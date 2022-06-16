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

use FiveTwo\DependencyInjection\Lifetime\LifetimeStrategy;
use FiveTwo\DependencyInjection\Provision\InstanceProvider;

/**
 * Default implementation for {@see ContainerBuilderInterface}.
 *
 * @psalm-require-implements ContainerBuilderInterface
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
     * @param LifetimeStrategy<TClass> $lifetimeStrategy The lifetime strategy to use to manage instances
     * @param InstanceProvider<TClass> $instanceProvider The instance provider to use to create new instances
     *
     * @return $this
     */
    public function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProvider $instanceProvider
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
            new ContainerDescriptor($container, $this->getInjector(), $lifetimeStrategyFactory)
        );

        return $this;
    }

    /**
     * @psalm-template TBuilder of ContainerBuilderInterface
     *
     * @param callable(static):mixed $builder
     * @psalm-param callable(TBuilder):mixed $builder
     *
     * @return $this
     */
    public function build(callable $builder): static
    {
        /**
         * Psalm and PHPStan don't think Container implements ContainerBuilderInterface?
         * @psalm-suppress InvalidArgument
         * @phpstan-ignore-next-line
         */
        $builder($this);

        return $this;
    }
}
