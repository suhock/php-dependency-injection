<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Closure;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionException;

/**
 * Provides instances of classes with the given attribute.
 *
 * @template TAttr of object
 */
class AttributeContainer implements ContainerInterface
{
    private readonly Closure $factory;

    private readonly InjectorInterface $injector;

    /**
     * @param class-string<TAttr> $attributeName The name of the attribute that must be present to enable this container
     * for a class
     * @param InjectorInterface|null $injector [optional] The injector to use for calling the factory method
     * @param callable|null $factory [optional] The factory to use for acquiring instances of classes. The first
     * argument will be the name of the class. The second argument will be an instance of the attribute attached to the
     * class. Additional arguments can be provided from this container's {@see Injector}. If no factory is provided, a
     * default factory that directly instantiates the class will be used.
     * <code>
     * callable&lt;TClass, TAttr&gt;(class-string&lt;TClass&gt; $className, TAttr $attr, ...): TClass
     * </code>
     */
    public function __construct(
        private readonly string $attributeName,
        ?InjectorInterface $injector = null,
        ?callable $factory = null
    ) {
        $this->injector = $injector ?? new ContainerInjector($this);
        $this->factory = $factory !== null ? $factory(...) : $this->instantiate(...);
    }

    /**
     * @template TClass of object
     *
     * @param class-string<TClass> $className
     * @param TAttr $attr
     *
     * @return TClass
     * @noinspection PhpUnusedParameterInspection
     */
    private function instantiate(string $className, object $attr): object
    {
        return $this->injector->instantiate($className);
    }

    /**
     * @inheritDoc
     * @template TClass of object
     *
     * @param class-string<TClass> $className The name of the class to retrieve
     *
     * @return TClass An instance of {@see $className}
     * @throws ClassNotFoundException If the specified class does not implement or extend {@see $interfaceName}
     */
    public function get(string $className): object
    {
        try {
            $rAttr = $this->getAttribute($className);
        } catch (ReflectionException $e) {
            throw new ClassNotFoundException($className, $e);
        }

        if ($rAttr === null) {
            throw new ClassNotFoundException($className);
        }

        return $this->injector->call($this->factory, [$className, $rAttr->newInstance()]);
    }

    /**
     * @inheritDoc
     */
    public function has(string $className): bool
    {
        try {
            return $this->getAttribute($className) !== null;
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * @param class-string $className
     * @return ReflectionAttribute<object>|null
     * @throws ReflectionException
     */
    private function getAttribute(string $className): ?ReflectionAttribute
    {
        $rClass = new ReflectionClass($className);
        $rAttributes = $rClass->getAttributes($this->attributeName);

        return $rAttributes[0] ?? null;
    }
}
