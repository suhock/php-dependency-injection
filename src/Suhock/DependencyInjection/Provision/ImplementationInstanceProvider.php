<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Provision;

use Suhock\DependencyInjection\ClassNotFoundException;
use Suhock\DependencyInjection\ContainerInterface;

/**
 * Factory that provides instances of a class by requesting an instance of a concrete implementation of that class from
 * the container.
 *
 * @template TClass of object
 * @template TImplementation of TClass
 * @template-implements InstanceProvider<TClass>
 */
class ImplementationInstanceProvider implements InstanceProvider
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
     * @return TClass An instance of the class
     * @psalm-return TClass An instance of the class
     * @phpstan-return TImplementation
     * @throws ClassNotFoundException If the container could not resolve a value for the specified class
     */
    public function get(): object
    {
        return $this->container->get($this->implementationClassName);
    }
}
