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
use Suhock\DependencyInjection\Lifetime\ScopedStrategy;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderFactory;
use Suhock\DependencyInjection\InstanceProvider\InstanceProviderInterface;
use UnitEnum;

/**
 * Default implementation for {@see ContainerScopedBuilderInterface}. Classes using this trait must implement
 * {@see ContainerBuilderInterface}.
 */
trait ContainerScopedBuilderTrait
{
    /**
     * @template TClass of object
     * @template TImplementation of TClass
     *
     * @param class-string<TClass> $className
     * @param class-string<TImplementation>|TClass|Closure|null $source
     *
     * @return $this
     */
    // @phpstan-ignore method.childParameterType (false positive on templated builder generics)
    public function addScoped(string $className, string|object|null $source = null): static
    {
        $this->addScopedInstanceProvider(
            $className,
            InstanceProviderFactory::createInstanceProvider($className, $source)
        );

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param class-string<TClass>|TClass|Closure|null $source
     */
    public function addKeyedScoped(
        string $className,
        string|UnitEnum $key,
        string|object|null $source = null
    ): static {
        return $this->addKeyed(
            $className,
            $key,
            new ScopedStrategy($className),
            InstanceProviderFactory::createInstanceProvider($className, $source)
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
    public function addScopedInstanceProvider(string $className, InstanceProviderInterface $instanceProvider): static
    {
        $this->add($className, new ScopedStrategy($className), $instanceProvider);

        return $this;
    }

    /**
     * @inheritDoc
     *
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param Closure|callable-string|null $mutator
     */
    // @phpstan-ignore method.childParameterType (false positive on templated builder generics)
    public function addScopedClass(string $className, ?callable $mutator = null): static
    {
        $this->addScopedInstanceProvider(
            $className,
            InstanceProviderFactory::createClassInstanceProvider($className, $mutator)
        );

        return $this;
    }

    /**
     * @template TClass of object
     * @template TImplementation of TClass
     */
    public function addScopedImplementation(string $className, string $implementationClassName): static
    {
        $this->addScopedInstanceProvider(
            $className,
            InstanceProviderFactory::createImplementationInstanceProvider($className, $implementationClassName)
        );

        return $this;
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     */
    public function addScopedFactory(string $className, callable $factory): static
    {
        $this->addScopedInstanceProvider(
            $className,
            InstanceProviderFactory::createClosureInstanceProvider($className, $factory(...))
        );

        return $this;
    }
}
