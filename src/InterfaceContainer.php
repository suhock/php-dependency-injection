<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use UnitEnum;

/**
 * Provides instances of classes that inherit from the given interface or base class.
 *
 * @template TInterface of object
 */
final class InterfaceContainer extends AbstractFactoryContainer
{
    /**
     * @param class-string<TInterface> $interfaceName The fully qualified name of the interface or base class
     * @param callable(ContainerInterface):InjectorInterface $injectorFactory Provides the injector to be used for
     * calling the factory method
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     * <code>
     * function&lt;TClass of TInterface&gt;(class-string&lt;TClass&gt; $className, [object ...]): TClass
     * </code>
     */
    public function __construct(
        private readonly string $interfaceName,
        callable $injectorFactory,
        ?callable $factory = null
    ) {
        parent::__construct($injectorFactory, $factory);
    }

    /**
     * Creates an interface container whose injector resolves dependencies from the container itself. To resolve
     * dependencies from an outer container instead, use the constructor and provide an injector backed by that
     * container.
     *
     * @template TDefault of object
     *
     * @param class-string<TDefault> $interfaceName The fully qualified name of the interface or base class
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes
     *
     * @return self<TDefault>
     */
    public static function createDefault(string $interfaceName, ?callable $factory = null): self
    {
        return new self(
            $interfaceName,
            static fn (ContainerInterface $container) => Injector::createDefault($container),
            $factory
        );
    }

    /**
     * @param class-string $className The name of the class to test
     *
     * @return bool <code>true</code> if the container can provide a service, <code>false</code> otherwise
     */
    public function has(string $className, string|UnitEnum|null $key = null): bool
    {
        return is_subclass_of($className, $this->interfaceName);
    }
}
