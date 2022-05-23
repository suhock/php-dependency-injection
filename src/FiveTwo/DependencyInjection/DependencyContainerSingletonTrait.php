<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use FiveTwo\DependencyInjection\Instantiation\ClassImplementationException;
use FiveTwo\DependencyInjection\Instantiation\ClassInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\ClosureInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\DependencyTypeException;
use FiveTwo\DependencyInjection\Instantiation\ImplementationInstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\InstanceFactory;
use FiveTwo\DependencyInjection\Instantiation\ObjectInstanceFactory;
use FiveTwo\DependencyInjection\Lifetime\SingletonStrategy;

/**
 * Provides convenience methods for adding singletons to a container.
 */
trait DependencyContainerSingletonTrait
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
    public function addSingleton(string $className, InstanceFactory $instanceFactory): static
    {
        self::addDescriptor(new DependencyDescriptor($className, new SingletonStrategy($className), $instanceFactory));

        return $this;
    }

    /**
     * @template TDependency
     * @template TImplementation of TDependency
     *
     * @param class-string<TDependency> $className
     * @param class-string<TImplementation> $implementationClassName
     *
     * @return $this
     * @throws ClassImplementationException
     * @psalm-param class-string<TImplementation>|'' $implementationClassName
     */
    public function addSingletonClass(string $className, string $implementationClassName = ''): static
    {
        self::addSingleton(
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
    public function addSingletonFactory(string $className, callable $factory): static
    {
        self::addSingleton(
            $className,
            new ClosureInstanceFactory($className, $factory(...), self::getInjector())
        );

        return $this;
    }

    /**
     * @template TDependency
     *
     * @param class-string<TDependency> $className
     * @param TDependency|null $instance
     *
     * @return $this
     * @throws DependencyTypeException
     */
    public function addSingletonInstance(string $className, ?object $instance): static
    {
        self::addSingleton(
            $className,
            new ObjectInstanceFactory($className, $instance)
        );

        return $this;
    }
}
