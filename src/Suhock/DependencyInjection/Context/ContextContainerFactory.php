<?php
/*
 * Copyright (c) 2022-2023 Matthew Suhocki. All rights reserved.
 *
 * This software is licensed under the terms of the MIT License <https://opensource.org/licenses/MIT>.
 * The above copyright notice and this notice shall be included in all copies or substantial portions of this software.
 */

declare(strict_types=1);

namespace Suhock\DependencyInjection\Context;

use Suhock\DependencyInjection\Container;
use Suhock\DependencyInjection\InjectorInterface;

/**
 * Provides factory methods for creating {@see ContextContainer} instances.
 */
class ContextContainerFactory
{
    private function __construct()
    {
    }

    /**
     * @return ContextContainer<Container> A {@see ContextContainer} using {@see Container}
     */
    public static function createForDefaultContainer(): ContextContainer
    {
        return new ContextContainer(fn (InjectorInterface $injector) => new Container($injector));
    }
}
