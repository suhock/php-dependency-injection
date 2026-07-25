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
    #[Override]
    public function addScoped(string $className, string|object|null $source = null): static
    {
        $this->addScopedInstanceProvider(
            $className,
            InstanceProviderFactory::createInstanceProvider($className, $source),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addKeyedScoped(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null,
    ): static {
        $this->addKeyedScopedInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createInstanceProvider($className, $source),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addScopedClass(string $className, ?callable $mutator = null): static
    {
        $this->addScopedInstanceProvider(
            $className,
            InstanceProviderFactory::createClassInstanceProvider($className, $mutator),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addKeyedScopedClass(
        string $className,
        string|UnitEnum $key,
        ?callable $mutator = null,
    ): static {
        $this->addKeyedScopedInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClassInstanceProvider($className, $mutator),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addScopedImplementation(string $className, string $implementationClassName): static
    {
        $this->addScopedInstanceProvider(
            $className,
            InstanceProviderFactory::createImplementationInstanceProvider($className, $implementationClassName),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function addKeyedScopedImplementation(
        string $className,
        string|UnitEnum $key,
        string $implementationClassName,
    ): static {
        $this->addKeyedScopedInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createImplementationInstanceProvider($className, $implementationClassName),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addScopedFactory(string $className, callable $factory): static
    {
        $this->addScopedInstanceProvider(
            $className,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...)),
        );

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function addKeyedScopedFactory(string $className, string|UnitEnum $key, callable $factory): static
    {
        $this->addKeyedScopedInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...)),
        );

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     */
    private function addScopedInstanceProvider(string $className, InstanceProviderInterface $instanceProvider): void
    {
        $this->add($className, new ScopedStrategy($className), $instanceProvider);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param InstanceProviderInterface<TClass> $instanceProvider
     */
    private function addKeyedScopedInstanceProvider(
        string $className,
        string|UnitEnum $key,
        InstanceProviderInterface $instanceProvider,
    ): void {
        $this->addKeyed($className, $key, new ScopedStrategy($className), $instanceProvider);
    }
}
