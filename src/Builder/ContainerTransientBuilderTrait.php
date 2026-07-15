<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use Closure;
use Suhock\DependencyInjection\ContainerBuilderInterface;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderFactory;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use Suhock\DependencyInjection\Lifetime\TransientStrategy;
use UnitEnum;

/**
 * Default implementation for {@see ContainerTransientBuilderInterface}. Classes using this trait must implement
 * {@see ContainerBuilderInterface}.
 */
trait ContainerTransientBuilderTrait
{
    abstract private function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): static;

    abstract private function addKeyed(
        string $className,
        string|UnitEnum $key,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): static;

    /**
     * @inheritDoc
     */
    public function addTransient(string $className, string|Closure|null $source = null): static
    {
        $this->addTransientInstanceProvider(
            $className,
            InstanceProviderFactory::createInstanceProvider($className, $source),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addKeyedTransient(
        string $className,
        string|UnitEnum $key,
        string|Closure|null $source = null,
    ): static {
        return $this->addKeyedTransientInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createInstanceProvider($className, $source),
        );
    }

    /**
     * @inheritDoc
     */
    public function addTransientClass(string $className, ?callable $mutator = null): static
    {
        $this->addTransientInstanceProvider(
            $className,
            InstanceProviderFactory::createClassInstanceProvider($className, $mutator),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addKeyedTransientClass(
        string $className,
        string|UnitEnum $key,
        ?callable $mutator = null,
    ): static {
        return $this->addKeyedTransientInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClassInstanceProvider($className, $mutator),
        );
    }

    /**
     * @inheritDoc
     */
    public function addTransientImplementation(string $className, string $implementationClassName): static
    {
        $this->addTransientInstanceProvider(
            $className,
            InstanceProviderFactory::createImplementationInstanceProvider($className, $implementationClassName),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addKeyedTransientImplementation(
        string $className,
        string|UnitEnum $key,
        string $implementationClassName,
    ): static {
        return $this->addKeyedTransientInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createImplementationInstanceProvider($className, $implementationClassName),
        );
    }

    /**
     * @inheritDoc
     */
    public function addTransientFactory(string $className, callable $factory): static
    {
        $this->addTransientInstanceProvider(
            $className,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...)),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addKeyedTransientFactory(string $className, string|UnitEnum $key, callable $factory): static
    {
        return $this->addKeyedTransientInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...)),
        );
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     *
     * @return $this
     */
    private function addTransientInstanceProvider(string $className, InstanceProviderInterface $instanceProvider): static
    {
        $this->add($className, new TransientStrategy($className), $instanceProvider);

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     *
     * @return $this
     */
    private function addKeyedTransientInstanceProvider(
        string $className,
        string|UnitEnum $key,
        InstanceProviderInterface $instanceProvider,
    ): static {
        return $this->addKeyed($className, $key, new TransientStrategy($className), $instanceProvider);
    }
}
