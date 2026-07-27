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
use Override;
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
    public function addTransient(
        string $className,
        string|Closure|null $source = null,
        bool $shouldDispose = true,
    ): static {
        $this->addTransientInstanceProvider(
            $className,
            InstanceProviderFactory::createInstanceProvider($className, $source),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addKeyedTransient(
        string $className,
        string|UnitEnum $key,
        string|Closure|null $source = null,
        bool $shouldDispose = true,
    ): static {
        $this->addKeyedTransientInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createInstanceProvider($className, $source),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addTransientFactory(string $className, callable $factory, bool $shouldDispose = true): static
    {
        $this->addTransientInstanceProvider(
            $className,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...)),
            $shouldDispose,
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    // @phpstan-ignore missingType.callable (parameters discovered at build-time)
    #[Override]
    public function addKeyedTransientFactory(
        string $className,
        string|UnitEnum $key,
        callable $factory,
        bool $shouldDispose = true,
    ): static {
        $this->addKeyedTransientInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...)),
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
    private function addTransientInstanceProvider(
        string $className,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): void {
        $this->add($className, new TransientStrategy($className), $instanceProvider, $shouldDispose);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service
     */
    private function addKeyedTransientInstanceProvider(
        string $className,
        string|UnitEnum $key,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): void {
        $this->addKeyed($className, $key, new TransientStrategy($className), $instanceProvider, $shouldDispose);
    }
}
