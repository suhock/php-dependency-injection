<?php

/*
 * Copyright (c) 2022-2026 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\InstanceProvider;

use Closure;
use Suhock\DependencyInjection\ResolutionContext;

/**
 * Factory that provides an instance derived from the current resolution context, such as the resolution root
 * itself, rather than one built from a class or a factory's return value. Used to auto-bind services the container
 * supplies about itself (for example, the resolution root or its scope factory) without requiring the application
 * to add them.
 *
 * @template TClass of object
 *
 * @template-implements InstanceProviderInterface<TClass>
 *
 * @internal
 */
final class ContextInstanceProvider implements InstanceProviderInterface
{
    /**
     * @param class-string<TClass> $className The name of the class this factory will provide
     * @param Closure(ResolutionContext): object $select The selector that derives the instance from the resolution
     * context
     */
    public function __construct(
        public readonly string $className,
        public readonly Closure $select,
    ) {}

}
