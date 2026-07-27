<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Builder;

use Override;
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
    ): void;

    abstract private function addKeyed(
        string $className,
        string|UnitEnum $key,
        LifetimeStrategy $lifetimeStrategy,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): void;

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addSingleton(string $className, string|object|null $source = null): static
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
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addKeyedSingleton(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null,
    ): static {
        $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createInstanceProvider($className, $source),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addSingletonFactory(string $className, callable $factory): static
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
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addKeyedSingletonFactory(string $className, string|UnitEnum $key, callable $factory): static
    {
        $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...)),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addSingletonInstance(string $className, object $instance, bool $shouldDispose = true): static
    {
        $this->addSingletonInstanceProvider(
            $className,
            InstanceProviderFactory::createObjectInstanceProvider($className, $instance),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addKeyedSingletonInstance(
        string $className,
        string|UnitEnum $key,
        object $instance,
        bool $shouldDispose = true,
    ): static {
        $this->addKeyedSingletonInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createObjectInstanceProvider($className, $instance),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service
     */
    private function addSingletonInstanceProvider(
        string $className,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): void {
        $this->add($className, new SingletonStrategy($className), $instanceProvider, $shouldDispose);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service
     */
    private function addKeyedSingletonInstanceProvider(
        string $className,
        string|UnitEnum $key,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): void {
        $this->addKeyed($className, $key, new SingletonStrategy($className), $instanceProvider, $shouldDispose);
    }
}
