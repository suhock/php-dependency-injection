<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\InstanceProvider;

/**
 * Narrows a provider to one member of the closed set, carrying its templated type with it for static analysis.
 *
 * @internal
 */
final class InstanceProviders
{
    /**
     * @template TClass of object
     *
     * @param InstanceProviderInterface<TClass>|null $provider
     *
     * @phpstan-assert-if-true ClassInstanceProvider<TClass> $provider
     */
    public static function isClass(?InstanceProviderInterface $provider): bool
    {
        return $provider instanceof ClassInstanceProvider;
    }

    /**
     * @template TClass of object
     *
     * @param InstanceProviderInterface<TClass>|null $provider
     *
     * @phpstan-assert-if-true ClosureInstanceProvider<TClass> $provider
     */
    public static function isClosure(?InstanceProviderInterface $provider): bool
    {
        return $provider instanceof ClosureInstanceProvider;
    }

    /**
     * @template TClass of object
     *
     * @param InstanceProviderInterface<TClass>|null $provider
     *
     * @phpstan-assert-if-true ImplementationInstanceProvider<TClass> $provider
     */
    public static function isImplementation(?InstanceProviderInterface $provider): bool
    {
        return $provider instanceof ImplementationInstanceProvider;
    }
}
