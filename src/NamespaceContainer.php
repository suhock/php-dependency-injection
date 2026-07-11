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
 * Provides instances of classes within the given namespace.
 */
final class NamespaceContainer extends AbstractFactoryContainer
{
    private readonly string $namespace;

    /**
     * @param string $namespace The namespace from which to provide class instances. An empty string indicates this
     * container should resolve classes from any namespace.
     * @param callable(ContainerInterface):InjectorInterface $injectorFactory Provides the injector to be used for
     * calling the factory method
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     * <code>
     * function(class-string&lt;T&gt; $className, ...): T
     * </code>
     */
    public function __construct(
        string $namespace,
        callable $injectorFactory,
        ?callable $factory = null
    ) {
        parent::__construct($injectorFactory, $factory);
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * Creates a namespace container whose injector resolves dependencies from the container itself. To resolve
     * dependencies from an outer container instead, use the constructor and provide an injector backed by that
     * container.
     *
     * @param string $namespace The namespace from which to provide class instances
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes
     */
    public static function createDefault(string $namespace, ?callable $factory = null): self
    {
        return new self(
            $namespace,
            static fn (ContainerInterface $container) => Injector::createDefault($container),
            $factory
        );
    }

    public function has(string $className, string|UnitEnum|null $key = null): bool
    {
        return $this->namespace === '' ||
            str_starts_with($className, $this->namespace . '\\');
    }
}
