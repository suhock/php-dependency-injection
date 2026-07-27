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
use Suhock\DependencyInjection\Lifetime\ScopedStrategy;
use UnitEnum;

/**
 * Default implementation for {@see ContainerScopedBuilderInterface}. Classes using this trait must implement
 * {@see ContainerBuilderInterface}.
 */
trait ContainerScopedBuilderTrait
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
    public function addScoped(
        string $className,
        string|Closure|null $source = null,
        bool $shouldDispose = true,
    ): static {
        $this->addScopedInstanceProvider(
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
    public function addKeyedScoped(
        string $className,
        string|UnitEnum $key,
        string|Closure|null $source = null,
        bool $shouldDispose = true,
    ): static {
        $this->addKeyedScopedInstanceProvider(
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
    public function addScopedFactory(string $className, callable $factory, bool $shouldDispose = true): static
    {
        $this->addScopedInstanceProvider(
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
    public function addKeyedScopedFactory(
        string $className,
        string|UnitEnum $key,
        callable $factory,
        bool $shouldDispose = true,
    ): static {
        $this->addKeyedScopedInstanceProvider(
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
    private function addScopedInstanceProvider(
        string $className,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): void {
        $this->add($className, new ScopedStrategy($className), $instanceProvider, $shouldDispose);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     * @param bool $shouldDispose Whether the container should dispose the disposable instances it creates for this
     *     service
     */
    private function addKeyedScopedInstanceProvider(
        string $className,
        string|UnitEnum $key,
        InstanceProviderInterface $instanceProvider,
        bool $shouldDispose = true,
    ): void {
        $this->addKeyed($className, $key, new ScopedStrategy($className), $instanceProvider, $shouldDispose);
    }
}
