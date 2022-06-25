<?php
/*
 * Copyright (c) 2022 Matthew Suhocki. All rights reserved.
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;
use ReflectionClass;
use ReflectionException;

use function count;

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
     * @param callable|null $factory [optional] A factory to use for acquiring instances of classes. The first argument
     * will be the name of the class. The second argument will be an instance of the attribute. Additional arguments can
     * be provided from this container's {@see Injector}. If no factory is provided, a default factory that directly
     * instantiates the class will be used.
     * <code>
     * function&lt;TClass, TAttr&gt;(class-string&lt;TClass&gt; $className, TAttr $attr, ...): TClass
     * </code>
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly string $attributeName,
        ?InjectorInterface $injector = null,
        ?callable $factory = null
    ) {
        $this->injector = $injector ?? new Injector($this);
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
     * @throws UnresolvedClassException If the specified class does not implement or extend {@see $interfaceName}
     */
    public function get(string $className): object
    {
        try {
            $rClass = new ReflectionClass($className);
            /** @phpstan-ignore-next-line PHPStan detects this as a dead catch */
        } catch (ReflectionException $e) {
            throw new UnresolvedClassException($className, $e);
        }

        $attr = $rClass->getAttributes($this->attributeName);

        if (count($attr) < 1) {
            throw new UnresolvedClassException($className);
        }

        /** @psalm-var TClass $instance Psalm needs help inferring type */
        $instance = $this->injector->call($this->factory, [$className, $attr[0]->newInstance()]);

        return $instance;
    }

    /**
     * @inheritDoc
     * @psalm-mutation-free
     */
    public function has(string $className): bool
    {
        try {
            $rClass = new ReflectionClass($className);
            /** @phpstan-ignore-next-line PHPStan detects this as a dead catch */
        } catch (ReflectionException) {
            return false;
        }

        return count($rClass->getAttributes($this->attributeName)) > 0;
    }
}
