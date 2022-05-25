<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\CircularDependencyException;
use FiveTwo\DependencyInjection\DependencyContainerInterface;
use FiveTwo\DependencyInjection\UnresolvedClassException;

/**
 * Provides a concrete implementation for a given interface or class.
 *
 * @template TDependency
 * @template TImplementation of TDependency
 * @template-implements InstanceFactory<TDependency>
 */
class ImplementationInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TDependency> $className The name of the class or interface provided
     * @param class-string<TImplementation> $implementationClassName The name of the class providing the concrete
     * implementation
     *
     * @throws ImplementationException If {@see $implementationClassName} is not a subclass of {@see $className}
     */
    public function __construct(
        string $className,
        private readonly string $implementationClassName,
        private readonly DependencyContainerInterface $container
    ) {
        if (!is_subclass_of($implementationClassName, $className)) {
            throw new ImplementationException($className, $this->implementationClassName);
        }
    }

    /**
     * @return TDependency|null
     * @throws ImplementationException
     * @throws CircularDependencyException
     * @throws UnresolvedClassException
     */
    public function get(): ?object
    {
        return $this->container->get($this->implementationClassName);
    }
}
