<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\ClassImplementationException;
use FiveTwo\DependencyInjection\Instantiation\ClassInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\ClosureInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\ImplementationInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\TransientStrategy;

/**
 * Provides convenience methods for adding transients to a container.
 */
trait DependencyContainerTransientTrait
{
    protected abstract function addDescriptor(DependencyDescriptor $descriptor): static;

    protected abstract function getInjector(): DependencyInjectorInterface;

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param InstanceFactory<TDependency> $instanceFactory
     *
     * @return $this
     */
    public function addTransient(string $className, InstanceFactory $instanceFactory): static
    {
        self::addDescriptor(new DependencyDescriptor($className, new TransientStrategy($className), $instanceFactory));

        return $this;
    }

    /**
     * @template TDependency
     * @template TImplementation of TDependency
     *
     * @param class-string<TDependency> $className
     * @param class-string<TImplementation>|'' $implementationClassName
     *
     * @return $this
     * @throws ClassImplementationException
     */
    public function addTransientClass(string $className, string $implementationClassName = ''): static
    {
        self::addTransient(
            $className,
            ($implementationClassName === $className || $implementationClassName === '') ?
                new ClassInstanceFactory($className, self::getInjector()) :
                new ImplementationInstanceFactory($className, $implementationClassName, $this)
        );

        return $this;
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param callable():TDependency $factory
     *
     * @return $this
     * @psalm-param callable(...):(TDependency|null) $factory
     */
    public function addTransientFactory(string $className, callable $factory): static
    {
        self::addTransient(
            $className,
            new ClosureInstanceFactory($className, $factory(...), self::getInjector())
        );

        return $this;
    }
}
