<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection\Instantiation;

use FiveTwo\DependencyInjection\ContainerInterface;
use FiveTwo\DependencyInjection\UnresolvedClassException;

/**
 * Provides a concrete implementation for a given interface or class from a container.
 *
 * @template TClass of object
 * @template TImplementation of TClass
 * @template-implements InstanceFactory<TClass>
 */
class ImplementationInstanceFactory implements InstanceFactory
{
    /**
     * @param class-string<TClass> $className The name of the class or interface provided
     * @param class-string<TImplementation> $implementationClassName The name of the class providing the implementation
     * instance
     *
     * @throws ImplementationException If {@see $implementationClassName} is not a subclass of {@see $className}
     */
    public function __construct(
        string $className,
        private readonly string $implementationClassName,
        private readonly ContainerInterface $container
    ) {
        if (!is_subclass_of($implementationClassName, $className)) {
            throw new ImplementationException($className, $this->implementationClassName);
        }
    }

    /**
     * @inheritDoc
     * @return TClass|null An instance of the class or <code>null</code>
     * @psalm-return TClass|null
     * @phpstan-return TImplementation|null
     * @throws UnresolvedClassException If the container could not resolve a value for the specified class
     */
    public function get(): ?object
    {
        return $this->container->get($this->implementationClassName);
    }
}
