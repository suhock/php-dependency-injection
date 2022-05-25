<?php
/*
 * Copyright (c) 2022 Five Two Foundation, LLC. All rights reserved.
 */

declare(strict_types=1);

namespace FiveTwo\DependencyInjection;

use Closure;

class NamespaceContainer implements DependencyContainerInterface
{
    private readonly string $namespace;

    /**
     * @template T
     *
     * @param string $namespace
     * @param Closure(class-string<T>):(T|null) $factory
     */
    public function __construct(
        string $namespace,
        private readonly Closure $factory
    ) {
        $this->namespace = trim($namespace, '\\');
    }

    /**
     * @inheritDoc
     * @throws UnresolvedClassException
     */
    public function get(string $className): ?object
    {
        return $this->has($className) ?
            ($this->factory)($className) :
            throw new UnresolvedClassException($className);
    }

    /**
     * @inheritDoc
     */
    public function has(string $className): bool
    {
        return $this->namespace === '' ||
            str_starts_with($className, $this->namespace . '\\');
    }
}
