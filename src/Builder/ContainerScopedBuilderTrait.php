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
    public function addScoped(string $className, string|object|null $source = null): self
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
    public function addKeyedScoped(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null,
    ): self {
        return $this->addKeyedScopedInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createInstanceProvider($className, $source),
        );
    }

    /**
     * @inheritDoc
     */
    public function addScopedClass(string $className, ?callable $mutator = null): self
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
    public function addKeyedScopedClass(
        string $className,
        string|UnitEnum $key,
        ?callable $mutator = null,
    ): self {
        return $this->addKeyedScopedInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createClassInstanceProvider($className, $mutator),
        );
    }

    /**
     * @inheritDoc
     */
    public function addScopedImplementation(string $className, string $implementationClassName): self
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
    ): self {
        return $this->addKeyedScopedInstanceProvider(
            $className,
            $key,
            InstanceProviderFactory::createImplementationInstanceProvider($className, $implementationClassName),
        );
    }

    /**
     * @inheritDoc
     */
    public function addScopedFactory(string $className, callable $factory): self
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
    public function addKeyedScopedFactory(string $className, string|UnitEnum $key, callable $factory): self
    {
        return $this->addKeyedScopedInstanceProvider(
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
    private function addScopedInstanceProvider(string $className, InstanceProviderInterface $instanceProvider): self
    {
        $this->add($className, new ScopedStrategy($className), $instanceProvider);

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
    private function addKeyedScopedInstanceProvider(
        string $className,
        string|UnitEnum $key,
        InstanceProviderInterface $instanceProvider,
    ): self {
        return $this->addKeyed($className, $key, new ScopedStrategy($className), $instanceProvider);
    }
}
