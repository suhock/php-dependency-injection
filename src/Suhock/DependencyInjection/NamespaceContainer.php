<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

/**
 * Provides instances of classes within the given namespace.
 */
class NamespaceContainer extends AbstractFactoryContainer
{
    private readonly string $namespace;

    /**
     * @param string $namespace The namespace from which to provide class instances. An empty string indicates this
     * container should resolve classes from any namespace.
     * @param InjectorInterface|null $injector [optional] The injector to use for calling the factory method
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. Additional arguments can be provided from this container's {@see Injector}. If no
     * factory is provided, a default factory that directly instantiates the class will be used.
     * <code>
     * function(class-string&lt;T&gt; $className, ...): T
     * </code>
     *
     * @psalm-mutation-free
     */
    public function __construct(
        string $namespace,
        ?InjectorInterface $injector = null,
        ?callable $factory = null
    ) {
        parent::__construct($injector, $factory);
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * @inheritDoc
     * @psalm-mutation-free
     */
    public function has(string $className): bool
    {
        return $this->namespace === '' ||
            str_starts_with($className, $this->namespace . '\\');
    }
}
