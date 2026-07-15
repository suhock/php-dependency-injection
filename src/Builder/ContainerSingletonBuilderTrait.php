<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use Suhock\DependencyInjection\ContainerBuilderInterface;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderFactory;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use Suhock\DependencyInjection\Lifetime\LifetimeStrategy;
use Suhock\DependencyInjection\Lifetime\SingletonStrategy;
use UnitEnum;

/**
 * Default implementation for {@see ContainerSingletonBuilderInterface}. Classes using this trait must implement
 * {@see ContainerBuilderInterface}.
 */
trait ContainerSingletonBuilderTrait
{
    abstract private function add(
        string $className,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): self;

    abstract private function addKeyed(
        string $className,
        string|UnitEnum $key,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): self;

    /**
     * @inheritDoc
     */
    public function addSingleton(string $className, string|object|null $source = null): self
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createInstanceProvider($className, $source),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addKeyedSingleton(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null,
    ): self {
        return $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createInstanceProvider($className, $source),
        );
    }

    /**
     * @inheritDoc
     */
    public function addSingletonClass(string $className, ?callable $mutator = null): self
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createClassInstanceProvider($className, $mutator),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addKeyedSingletonClass(
        string $className,
        string|UnitEnum $key,
        ?callable $mutator = null,
    ): self {
        return $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClassInstanceProvider($className, $mutator),
        );
    }

    /**
     * @inheritDoc
     */
    public function addSingletonImplementation(string $className, string $implementationClassName): self
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createImplementationInstanceProvider($className, $implementationClassName),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addKeyedSingletonImplementation(
        string $className,
        string|UnitEnum $key,
        string $implementationClassName,
    ): self {
        return $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createImplementationInstanceProvider($className, $implementationClassName),
        );
    }

    /**
     * @inheritDoc
     */
    public function addSingletonFactory(string $className, callable $factory): self
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...)),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addKeyedSingletonFactory(string $className, string|UnitEnum $key, callable $factory): self
    {
        return $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...)),
        );
    }

    /**
     * @inheritDoc
     */
    public function addSingletonInstance(string $className, object $instance, bool $shouldDispose = true): self
    {
        return $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createObjectInstanceProvider($className, $instance),
            $shouldDispose,
        );
    }

    /**
     * @inheritDoc
     */
    public function addKeyedSingletonInstance(
        string $className,
        string|UnitEnum $key,
        object $instance,
        bool $shouldDispose = true,
    ): self {
        return $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createObjectInstanceProvider($className, $instance),
            $shouldDispose,
        );
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service
     *
     * @return $this
     */
    private function addSingletonInstanceProvider(
        string $className,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): self {
        $this->add($className, new SingletonStrategy($className), $instanceProvider, $shouldDispose);

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service
     *
     * @return $this
     */
    private function addKeyedSingletonInstanceProvider(
        string $className,
        string|UnitEnum $key,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): self {
        return $this->addKeyed($className, $key, new SingletonStrategy($className), $instanceProvider, $shouldDispose);
    }
}
