<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection;

use Closure;
use Suhock\DependencyInjection\Lifetime\InstanceStore;
use UnitEnum;

/**
 * A scope created by {@see Container::createScope()}. The scope shares the root container's service descriptors but
 * owns its own instance store, so scoped services are instantiated once per scope and their dependencies
 * resolve from the scope. Singleton services continue to resolve from the root container regardless of which scope
 * requests them.
 *
 * @internal Obtain instances through {@see ScopeFactoryInterface::createScope()}; see {@see ScopeInterface} for the
 * public contract.
 */
final class Scope implements ScopeInterface
{
    private readonly ResolutionContext $resolutionContext;

    /** @var Closure(class-string, string|UnitEnum|null, ResolutionContext):object */
    private readonly Closure $resolve;

    private bool $disposed = false;

    /**
     * @param Container $rootContainer The container the scope was created from
     * @param ResolutionContext $rootContext The resolution context of the root container
     * @param callable(class-string, string|UnitEnum|null, ResolutionContext):object $resolve Resolves a service from
     * the root container for this scope's resolution context
     */
    public function __construct(
        private readonly Container $rootContainer,
        ResolutionContext $rootContext,
        callable $resolve
    ) {
        $this->resolve = $resolve(...);
        $this->resolutionContext = new ResolutionContext($this, new InstanceStore(), $rootContext);
    }

    /**
     * @inheritDoc
     * @throws ScopeException If the scope has been disposed
     */
    public function get(string $className, string|UnitEnum|null $key = null): object
    {
        $this->ensureNotDisposed();

        // @phpstan-ignore return.type (resolver returns the requested TClass, widened to object through the stored closure)
        return ($this->resolve)($className, $key, $this->resolutionContext);
    }

    /**
     * @inheritDoc
     * @throws ScopeException If the scope has been disposed
     */
    public function has(string $className, string|UnitEnum|null $key = null): bool
    {
        $this->ensureNotDisposed();

        return $this->rootContainer->has($className, $key);
    }

    public function dispose(): void
    {
        if ($this->disposed) {
            return;
        }

        // Mark disposed before sweeping so that a disposer resolving from this scope fails fast with a ScopeException
        // rather than resurrecting instances from a store that is being torn down.
        $this->disposed = true;
        $this->resolutionContext->store->dispose();
    }

    private function ensureNotDisposed(): void
    {
        if ($this->disposed) {
            throw new ScopeException('Scope has been disposed');
        }
    }
}
